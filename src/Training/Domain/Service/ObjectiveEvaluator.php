<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Service;

use Cadence\Training\Domain\Enum\ObjectiveType;
use Cadence\Training\Domain\Model\Objective;
use Cadence\Training\Domain\ValueObject\ActivitySummary;
use Cadence\Training\Domain\ValueObject\ObjectiveResult;

/**
 * Pure "coach" logic: decides whether an objective is achieved from the assigned
 * activities, and how far along it is (0..1). No I/O, no framework.
 */
final class ObjectiveEvaluator
{
    /**
     * @param list<ActivitySummary> $activities
     */
    public static function evaluate(Objective $objective, array $activities): ObjectiveResult
    {
        return match ($objective->type) {
            ObjectiveType::RACE_TIME => self::raceTime($objective, $activities),
            ObjectiveType::PACE_OVER_DISTANCE => self::paceOverDistance($objective, $activities),
            ObjectiveType::LONGEST_RUN => self::longestRun($objective, $activities),
            ObjectiveType::TOTAL_VOLUME => self::totalVolume($objective, $activities),
            ObjectiveType::SESSION_COUNT => self::sessionCount($objective, $activities),
        };
    }

    /** @param list<ActivitySummary> $activities */
    private static function raceTime(Objective $o, array $activities): ObjectiveResult
    {
        $distance = $o->targetDistanceMeters ?? 0;
        $target = $o->targetSeconds ?? 0;
        $bestTime = null;
        $bestBy = null;

        foreach ($activities as $a) {
            if ($a->distanceMeters >= $distance * 0.99 && ($bestTime === null || $a->movingSeconds < $bestTime)) {
                $bestTime = $a->movingSeconds;
                $bestBy = $a->activityId;
            }
            foreach ($a->bestEfforts as $effort) {
                if ($effort['distanceMeters'] >= $distance * 0.99 && ($bestTime === null || $effort['durationSeconds'] < $bestTime)) {
                    $bestTime = $effort['durationSeconds'];
                    $bestBy = $a->activityId;
                }
            }
        }

        if ($bestTime === null) {
            return new ObjectiveResult($o->id, $o->label, $o->type->value, false, 0.0, 'Aucune sortie à cette distance.');
        }

        $achieved = $bestTime <= $target;
        $progress = self::clamp($target / $bestTime);
        $detail = 'Meilleur : '.self::time($bestTime).' (objectif '.self::time($target).')';

        return new ObjectiveResult($o->id, $o->label, $o->type->value, $achieved, $achieved ? 1.0 : $progress, $detail, $achieved ? $bestBy : null);
    }

    /** @param list<ActivitySummary> $activities */
    private static function paceOverDistance(Objective $o, array $activities): ObjectiveResult
    {
        $distance = $o->targetDistanceMeters ?? 0;
        $target = $o->targetPaceSecondsPerKm ?? 0.0;
        $bestPace = null;
        $bestBy = null;

        foreach ($activities as $a) {
            if ($a->distanceMeters >= $distance * 0.99 && ($bestPace === null || $a->averagePaceSecondsPerKm < $bestPace)) {
                $bestPace = $a->averagePaceSecondsPerKm;
                $bestBy = $a->activityId;
            }
        }

        if ($bestPace === null || $target <= 0.0) {
            return new ObjectiveResult($o->id, $o->label, $o->type->value, false, 0.0, 'Aucune sortie à cette distance.');
        }

        $achieved = $bestPace <= $target;
        $progress = self::clamp($target / $bestPace);
        $detail = 'Meilleure allure : '.self::pace($bestPace).' (objectif '.self::pace($target).')';

        return new ObjectiveResult($o->id, $o->label, $o->type->value, $achieved, $achieved ? 1.0 : $progress, $detail, $achieved ? $bestBy : null);
    }

    /** @param list<ActivitySummary> $activities */
    private static function longestRun(Objective $o, array $activities): ObjectiveResult
    {
        $distance = $o->targetDistanceMeters ?? 0;
        $longest = 0;
        $by = null;

        foreach ($activities as $a) {
            if ($a->distanceMeters > $longest) {
                $longest = $a->distanceMeters;
                $by = $a->activityId;
            }
        }

        $achieved = $distance > 0 && $longest >= $distance;
        $progress = $distance > 0 ? self::clamp($longest / $distance) : 0.0;
        $detail = 'Plus longue : '.self::km($longest).' km (objectif '.self::km($distance).' km)';

        return new ObjectiveResult($o->id, $o->label, $o->type->value, $achieved, $progress, $detail, $achieved ? $by : null);
    }

    /** @param list<ActivitySummary> $activities */
    private static function totalVolume(Objective $o, array $activities): ObjectiveResult
    {
        $target = $o->targetDistanceMeters ?? 0;
        $total = array_sum(array_map(static fn (ActivitySummary $a): int => $a->distanceMeters, $activities));

        $achieved = $target > 0 && $total >= $target;
        $progress = $target > 0 ? self::clamp($total / $target) : 0.0;
        $detail = self::km($total).' km cumulés (objectif '.self::km($target).' km)';

        return new ObjectiveResult($o->id, $o->label, $o->type->value, $achieved, $progress, $detail);
    }

    /** @param list<ActivitySummary> $activities */
    private static function sessionCount(Objective $o, array $activities): ObjectiveResult
    {
        $target = $o->targetCount ?? 0;
        $count = count($activities);

        $achieved = $target > 0 && $count >= $target;
        $progress = $target > 0 ? self::clamp($count / $target) : 0.0;
        $detail = $count.' séance(s) sur '.$target;

        return new ObjectiveResult($o->id, $o->label, $o->type->value, $achieved, $progress, $detail);
    }

    private static function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    private static function time(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }

    private static function pace(float $secondsPerKm): string
    {
        $r = (int) round($secondsPerKm);

        return sprintf('%d:%02d/km', intdiv($r, 60), $r % 60);
    }

    private static function km(int $meters): string
    {
        return number_format($meters / 1000, 2, ',', ' ');
    }
}
