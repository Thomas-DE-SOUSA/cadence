<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Read;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Coaching\Domain\Service\VdotCalculator;
use DateTimeImmutable;

/**
 * Builds the progression screen: personal records per distance, progression
 * curves over time, the sub-40 goal tracker with a race countdown, a current
 * VDOT estimate and a Riegel time-projection to the goal distance.
 */
final class ProgressionView
{
    /** Distances (m) we plot progression curves for, when data allows. */
    private const CURVE_DISTANCES = [1000, 5000, 10000, 21100];

    /**
     * @param list<ActivityModel> $models most recent first
     * @param array{label:string,raceName:string|null,raceDate:string|null,distanceMeters:int,targetSeconds:int}|null $goal
     *
     * @return array<string, mixed>
     */
    public static function build(array $models, DateTimeImmutable $today, ?array $goal = null): array
    {
        $bests = self::bestsByDistance($models);
        $records = self::records($bests);
        $focusDistance = $goal['distanceMeters'] ?? 10000;

        return [
            'goal' => self::goal($goal, $bests, $today),
            'records' => $records,
            'series' => self::series($models, $goal),
            'focusDistance' => $focusDistance,
            'projection' => self::projection($bests, $goal),
            'vdot' => self::vdot($bests),
            'stats' => self::stats($models),
        ];
    }

    /**
     * Best effort per distance across every run.
     *
     * @param list<ActivityModel> $models
     *
     * @return array<int, array{duration:int,activityId:string,occurredAt:string}>
     */
    private static function bestsByDistance(array $models): array
    {
        /** @var array<int, array{duration:int,activityId:string,occurredAt:string}> $bests */
        $bests = [];
        foreach ($models as $m) {
            foreach (EffortCalculator::forActivity($m) as $e) {
                $d = $e['distance'];
                if (! isset($bests[$d]) || $e['duration'] < $bests[$d]['duration']) {
                    $bests[$d] = [
                        'duration' => $e['duration'],
                        'activityId' => $m->id,
                        'occurredAt' => (string) $m->occurred_at,
                    ];
                }
            }
        }
        ksort($bests);

        return $bests;
    }

    /**
     * @param array<int, array{duration:int,activityId:string,occurredAt:string}> $bests
     *
     * @return list<array{label:string,distanceMeters:int,durationSeconds:int,paceSecondsPerKm:int,activityId:string,occurredAt:string}>
     */
    private static function records(array $bests): array
    {
        $records = [];
        foreach ($bests as $distance => $best) {
            $records[] = [
                'label' => self::distanceLabel($distance),
                'distanceMeters' => $distance,
                'durationSeconds' => $best['duration'],
                'paceSecondsPerKm' => (int) round($best['duration'] / ($distance / 1000)),
                'activityId' => $best['activityId'],
                'occurredAt' => $best['occurredAt'],
            ];
        }

        return $records;
    }

    /**
     * @param array{label:string,raceName:string|null,raceDate:string|null,distanceMeters:int,targetSeconds:int}|null $goal
     * @param array<int, array{duration:int,activityId:string,occurredAt:string}> $bests
     *
     * @return array<string, mixed>|null
     */
    private static function goal(?array $goal, array $bests, DateTimeImmutable $today): ?array
    {
        if ($goal === null) {
            return null;
        }

        $best = $bests[$goal['distanceMeters']] ?? null;
        $current = $best['duration'] ?? null;
        $target = $goal['targetSeconds'];
        $achieved = $current !== null && $current <= $target;

        $daysLeft = null;
        $weeksLeft = null;
        if ($goal['raceDate'] !== null) {
            $race = new DateTimeImmutable($goal['raceDate']);
            $days = (int) $today->setTime(0, 0)->diff($race->setTime(0, 0))->format('%r%a');
            $daysLeft = $days;
            $weeksLeft = (int) ceil(max(0, $days) / 7);
        }

        return [
            'label' => $goal['label'],
            'raceName' => $goal['raceName'],
            'raceDate' => $goal['raceDate'],
            'daysLeft' => $daysLeft,
            'weeksLeft' => $weeksLeft,
            'distanceMeters' => $goal['distanceMeters'],
            'distanceLabel' => self::distanceLabel($goal['distanceMeters']),
            'targetSeconds' => $target,
            'currentSeconds' => $current,
            'currentActivityId' => $best['activityId'] ?? null,
            'achieved' => $achieved,
            'gapSeconds' => $current === null ? null : max(0, $current - $target),
            'progressPct' => $current === null ? 0 : ($achieved ? 100 : (int) min(99, round($target / $current * 100))),
        ];
    }

    /**
     * Progression curves: for each distance with at least one effort, the best
     * effort per run over time (oldest first).
     *
     * @param list<ActivityModel> $models
     * @param array{distanceMeters:int,targetSeconds:int}|array<string,mixed>|null $goal
     *
     * @return list<array{distanceMeters:int,label:string,targetSeconds:int|null,points:list<array{date:string,seconds:int,pace:int}>}>
     */
    private static function series(array $models, ?array $goal): array
    {
        $ascending = array_reverse($models);
        $goalDistance = $goal['distanceMeters'] ?? null;
        $goalTarget = $goal['targetSeconds'] ?? null;

        $series = [];
        foreach (self::CURVE_DISTANCES as $distance) {
            $points = [];
            foreach ($ascending as $m) {
                foreach (EffortCalculator::forActivity($m) as $e) {
                    if ($e['distance'] === $distance) {
                        $points[] = [
                            'date' => substr((string) $m->occurred_at, 0, 10),
                            'seconds' => $e['duration'],
                            'pace' => (int) round($e['duration'] / ($distance / 1000)),
                        ];
                        break;
                    }
                }
            }
            // Keep a curve when it has real history, or when it is the goal
            // distance (so the target line still gives context with one point).
            if (count($points) >= 2 || ($distance === $goalDistance && $points !== [])) {
                $series[] = [
                    'distanceMeters' => $distance,
                    'label' => self::distanceLabel($distance),
                    'targetSeconds' => $distance === $goalDistance ? $goalTarget : null,
                    'points' => $points,
                ];
            }
        }

        return $series;
    }

    /**
     * Riegel projection to the goal distance from the best shorter effort:
     * T2 = T1 · (D2 / D1) ^ 1.06.
     *
     * @param array<int, array{duration:int,activityId:string,occurredAt:string}> $bests
     * @param array{distanceMeters:int,targetSeconds:int}|array<string,mixed>|null $goal
     *
     * @return array{fromLabel:string,fromDistanceMeters:int,toLabel:string,toDistanceMeters:int,predictedSeconds:int,beatsTarget:bool}|null
     */
    private static function projection(array $bests, ?array $goal): ?array
    {
        if ($goal === null) {
            return null;
        }
        $goalDistance = $goal['distanceMeters'];

        // Prefer the longest effort still shorter than the goal (most reliable).
        $base = null;
        foreach ($bests as $distance => $best) {
            if ($distance < $goalDistance) {
                $base = ['distance' => $distance, 'duration' => $best['duration']];
            }
        }
        if ($base === null) {
            return null;
        }

        $predicted = (int) round($base['duration'] * ($goalDistance / $base['distance']) ** 1.06);

        return [
            'fromLabel' => self::distanceLabel($base['distance']),
            'fromDistanceMeters' => $base['distance'],
            'toLabel' => self::distanceLabel($goalDistance),
            'toDistanceMeters' => $goalDistance,
            'predictedSeconds' => $predicted,
            'beatsTarget' => $predicted <= $goal['targetSeconds'],
        ];
    }

    /**
     * Current VDOT estimate from the best effort (prefer 10k, then 5k, then the
     * longest available), using the Daniels–Gilbert model.
     *
     * @param array<int, array{duration:int,activityId:string,occurredAt:string}> $bests
     */
    private static function vdot(array $bests): ?float
    {
        $pick = null;
        foreach ([10000, 5000, 21100, 3000, 1609, 1000] as $preferred) {
            if (isset($bests[$preferred])) {
                $pick = ['distance' => $preferred, 'duration' => $bests[$preferred]['duration']];
                break;
            }
        }
        if ($pick === null) {
            // Fall back to the longest effort we have.
            foreach ($bests as $distance => $best) {
                $pick = ['distance' => $distance, 'duration' => $best['duration']];
            }
        }
        if ($pick === null) {
            return null;
        }

        return round((new VdotCalculator())->vdot($pick['distance'], $pick['duration']), 1);
    }

    /**
     * @param list<ActivityModel> $models
     *
     * @return array{runs:int,totalDistanceMeters:int}
     */
    private static function stats(array $models): array
    {
        $total = 0;
        foreach ($models as $m) {
            $total += (int) $m->distance_meters;
        }

        return ['runs' => count($models), 'totalDistanceMeters' => $total];
    }

    private static function distanceLabel(int $meters): string
    {
        return match (true) {
            $meters === 42000, $meters === 42195 => 'Marathon',
            $meters === 21000, $meters === 21100 => 'Semi',
            $meters === 3219 => '2 miles',
            $meters === 1609 => '1 mile',
            $meters % 1000 === 0 => intdiv($meters, 1000).' km',
            default => number_format($meters / 1000, 2, ',', ' ').' km',
        };
    }
}
