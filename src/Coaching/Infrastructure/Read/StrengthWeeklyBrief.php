<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Read;

use Cadence\Coaching\Domain\ValueObject\StrengthWeekSummary;
use Cadence\Strength\Domain\Enum\MuscleGroup;
use Cadence\Strength\Domain\Model\StrengthSession;
use Cadence\Strength\Domain\ValueObject\PerformedExercise;
use Cadence\Strength\Domain\ValueObject\WeightEntry;
use DateTimeImmutable;

/**
 * Summarises the strength (muscu) week for the weekly coach: aggregates done
 * sessions into the current Mon–Sun week and the previous one, computes muscle
 * balance and leg load, and folds in the body-weight averages. Pure over
 * domain objects — the provider fetches, this shapes.
 */
final class StrengthWeeklyBrief
{
    private const LEG_MUSCLES = [
        MuscleGroup::QUADS->value,
        MuscleGroup::HAMSTRINGS->value,
        MuscleGroup::GLUTES->value,
        MuscleGroup::CALVES->value,
    ];

    /**
     * @param list<StrengthSession>       $sessions   any status; only DONE ones count
     * @param array<string, MuscleGroup>  $muscleOf   exercise id → its primary muscle
     * @param list<WeightEntry>           $weight     body-weight readings
     */
    public static function summarise(array $sessions, array $muscleOf, array $weight, string $today): StrengthWeekSummary
    {
        $monday = (new DateTimeImmutable($today))->modify('monday this week');
        $weekStart = $monday->format('Y-m-d');
        $weekEnd = $monday->modify('+6 days')->format('Y-m-d');
        $prevStart = $monday->modify('-7 days')->format('Y-m-d');
        $prevEnd = $monday->modify('-1 day')->format('Y-m-d');

        $sessionsCount = 0;
        $prevSessions = 0;
        $tonnage = 0.0;
        $prevTonnage = 0.0;
        $workingSets = 0;
        $legSessions = 0;
        /** @var array<string, int> $perMuscle */
        $perMuscle = [];
        $lastDate = null;

        foreach ($sessions as $session) {
            if (! $session->status()->isDone()) {
                continue;
            }

            $snap = $session->toSnapshot();
            $date = (string) $snap['date'];

            if ($date <= $today && ($lastDate === null || $date > $lastDate)) {
                $lastDate = $date;
            }

            if ($date >= $prevStart && $date <= $prevEnd) {
                $prevSessions++;
                $prevTonnage += $session->totalVolumeKg();

                continue;
            }

            if ($date < $weekStart || $date > $weekEnd) {
                continue;
            }

            $sessionsCount++;
            $tonnage += $session->totalVolumeKg();
            $touchesLegs = false;

            $rawExercises = is_array($snap['exercises']) ? $snap['exercises'] : [];
            foreach ($rawExercises as $raw) {
                if (! is_array($raw)) {
                    continue;
                }
                $exercise = PerformedExercise::fromArray($raw);
                $muscle = $muscleOf[$exercise->exerciseId] ?? null;
                if ($muscle === null) {
                    continue;
                }
                $sets = count($exercise->workingSets());
                $workingSets += $sets;
                $perMuscle[$muscle->value] = ($perMuscle[$muscle->value] ?? 0) + $sets;
                if (in_array($muscle->value, self::LEG_MUSCLES, true)) {
                    $touchesLegs = true;
                }
            }

            if ($touchesLegs) {
                $legSessions++;
            }
        }

        arsort($perMuscle);
        $balance = [];
        foreach ($perMuscle as $muscle => $sets) {
            $balance[] = ['muscle' => $muscle, 'label' => MuscleGroup::from($muscle)->label(), 'sets' => $sets];
        }

        return new StrengthWeekSummary(
            $weekStart,
            $weekEnd,
            $sessionsCount,
            $workingSets,
            round($tonnage, 1),
            $legSessions,
            $prevSessions,
            round($prevTonnage, 1),
            $lastDate === null ? null : self::daysBetween($lastDate, $today),
            $balance,
            self::averageKg($weight, $weekStart, $weekEnd),
            self::averageKg($weight, $prevStart, $prevEnd),
        );
    }

    /** @param list<WeightEntry> $weight */
    private static function averageKg(array $weight, string $from, string $to): ?float
    {
        $sum = 0.0;
        $count = 0;
        foreach ($weight as $entry) {
            if ($entry->date >= $from && $entry->date <= $to) {
                $sum += $entry->weightKg;
                $count++;
            }
        }

        return $count === 0 ? null : round($sum / $count, 1);
    }

    private static function daysBetween(string $from, string $to): int
    {
        return (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days;
    }
}
