<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Read;

use Cadence\Strength\Domain\Enum\Equipment;
use Cadence\Strength\Domain\Enum\MuscleGroup;
use Cadence\Strength\Domain\Model\Exercise;
use Cadence\Strength\Domain\Model\StrengthSession;
use Cadence\Strength\Domain\Service\OneRepMaxCalculator;
use Cadence\Strength\Domain\ValueObject\PerformedExercise;

/** Shapes strength data for the Muscu pages. Pure presentation over snapshots. */
final class StrengthView
{
    /**
     * @param list<Exercise> $exercises
     *
     * @return list<array<string, mixed>>
     */
    public static function catalog(array $exercises): array
    {
        return array_map(static fn (Exercise $e): array => [
            'id' => $e->id,
            'name' => $e->name,
            'muscle' => $e->primaryMuscle->value,
            'muscleLabel' => $e->primaryMuscle->label(),
            'equipment' => $e->equipment->value,
            'equipmentLabel' => $e->equipment->label(),
            'isCustom' => $e->isCustom,
        ], $exercises);
    }

    /** @return array{muscles:list<array{value:string,label:string}>,equipments:list<array{value:string,label:string}>} */
    public static function enums(): array
    {
        return [
            'muscles' => array_map(static fn (MuscleGroup $m): array => ['value' => $m->value, 'label' => $m->label()], MuscleGroup::cases()),
            'equipments' => array_map(static fn (Equipment $e): array => ['value' => $e->value, 'label' => $e->label()], Equipment::cases()),
        ];
    }

    /**
     * @param list<StrengthSession> $sessions
     *
     * @return list<array<string, mixed>>
     */
    public static function summaries(array $sessions): array
    {
        return array_map(static function (StrengthSession $s): array {
            $snap = $s->toSnapshot();

            return [
                'id' => $s->id(),
                'date' => $snap['date'],
                'title' => $snap['title'],
                'exerciseCount' => count($snap['exercises']),
                'totalSets' => $s->totalSets(),
                'volumeKg' => (int) round($s->totalVolumeKg()),
                'durationSeconds' => $snap['duration_seconds'],
            ];
        }, $sessions);
    }

    /** @return array<string, mixed> full session for the editor */
    public static function detail(StrengthSession $s): array
    {
        $snap = $s->toSnapshot();

        return [
            'id' => $s->id(),
            'date' => $snap['date'],
            'title' => $snap['title'],
            'note' => $snap['note'],
            'durationSeconds' => $snap['duration_seconds'],
            'exercises' => $snap['exercises'],
        ];
    }

    /**
     * Per-exercise progression: best estimated 1RM and its series over time.
     *
     * @param list<StrengthSession> $sessions
     *
     * @return list<array<string, mixed>>
     */
    public static function progression(array $sessions, OneRepMaxCalculator $calc): array
    {
        /** @var array<string, array{name:string,best:float,series:list<array{date:string,e1rm:int,topWeight:float}>}> $byExercise */
        $byExercise = [];

        // Oldest first so the series reads left → right.
        $ordered = $sessions;
        usort($ordered, static fn (StrengthSession $a, StrengthSession $b): int => strcmp($a->toSnapshot()['date'], $b->toSnapshot()['date']));

        foreach ($ordered as $session) {
            $snap = $session->toSnapshot();
            foreach ($snap['exercises'] as $rawExercise) {
                if (! is_array($rawExercise)) {
                    continue;
                }
                $exercise = PerformedExercise::fromArray($rawExercise);
                $e1rm = $calc->bestForExercise($exercise);
                if ($e1rm <= 0.0) {
                    continue; // bodyweight / timed — no 1RM to track
                }

                $topWeight = 0.0;
                foreach ($exercise->workingSets() as $set) {
                    $topWeight = max($topWeight, $set->weightKg ?? 0.0);
                }

                $key = $exercise->exerciseId;
                if (! isset($byExercise[$key])) {
                    $byExercise[$key] = ['name' => $exercise->name, 'best' => 0.0, 'series' => []];
                }
                $byExercise[$key]['best'] = max($byExercise[$key]['best'], $e1rm);
                $byExercise[$key]['series'][] = ['date' => $snap['date'], 'e1rm' => (int) round($e1rm), 'topWeight' => round($topWeight, 1)];
            }
        }

        $out = [];
        foreach ($byExercise as $id => $data) {
            $out[] = [
                'exerciseId' => $id,
                'name' => $data['name'],
                'bestE1rm' => (int) round($data['best']),
                'series' => $data['series'],
            ];
        }

        // Most-trained / heaviest first.
        usort($out, static fn (array $a, array $b): int => $b['bestE1rm'] <=> $a['bestE1rm']);

        return $out;
    }

    /**
     * The most recent performance of each exercise, to prefill "repeat last time".
     *
     * @param list<StrengthSession> $sessions most recent first
     *
     * @return array<string, array<string, mixed>> exerciseId → PerformedExercise array
     */
    public static function lastByExercise(array $sessions): array
    {
        $last = [];
        foreach ($sessions as $session) {
            foreach ($session->toSnapshot()['exercises'] as $rawExercise) {
                if (is_array($rawExercise) && isset($rawExercise['exercise_id'])) {
                    $id = (string) $rawExercise['exercise_id'];
                    if (! isset($last[$id])) {
                        $last[$id] = $rawExercise;
                    }
                }
            }
        }

        return $last;
    }
}
