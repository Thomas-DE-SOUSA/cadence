<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Service;

use Cadence\Strength\Domain\Enum\ProgressionKind;
use Cadence\Strength\Domain\Model\StrengthSession;
use Cadence\Strength\Domain\ValueObject\ExerciseProgression;
use Cadence\Strength\Domain\ValueObject\PerformedExercise;
use Cadence\Strength\Domain\ValueObject\SetEntry;

/**
 * Detects, per exercise, whether a session beat the last time that exercise was
 * performed — a heavier top working set (WEIGHT), or more reps at the same top
 * load (REPS, which also covers bodyweight exercises). The comparison runs on
 * the exercise's top working set (heaviest, ties broken by reps), so warm-ups
 * and set-count changes never create a false progression. Pure domain logic.
 */
final class SessionProgressionComparator
{
    /**
     * @param array<string, PerformedExercise> $previousByExercise last performance per exercise id
     *
     * @return list<ExerciseProgression>
     */
    public function compareSession(StrengthSession $current, array $previousByExercise): array
    {
        $out = [];
        foreach ($current->exercises() as $exercise) {
            $progression = $this->compare($exercise, $previousByExercise[$exercise->exerciseId] ?? null);
            if ($progression !== null) {
                $out[] = $progression;
            }
        }

        return $out;
    }

    public function compare(PerformedExercise $current, ?PerformedExercise $previous): ?ExerciseProgression
    {
        if ($previous === null) {
            return null; // first time: nothing to beat
        }

        $now = $this->topWorkingSet($current);
        $before = $this->topWorkingSet($previous);
        if ($now === null || $before === null) {
            return null; // no working set on one side (all warm-ups / empty)
        }

        $nowWeight = $now->weightKg ?? 0.0;
        $beforeWeight = $before->weightKg ?? 0.0;
        $nowReps = $now->reps ?? 0;
        $beforeReps = $before->reps ?? 0;

        if ($nowWeight > $beforeWeight) {
            return new ExerciseProgression(
                $current->exerciseId,
                $current->name,
                ProgressionKind::WEIGHT,
                $before->weightKg,
                $now->weightKg,
                $beforeReps,
                $nowReps,
            );
        }

        if ($nowWeight === $beforeWeight && $nowReps > $beforeReps) {
            return new ExerciseProgression(
                $current->exerciseId,
                $current->name,
                ProgressionKind::REPS,
                $before->weightKg,
                $now->weightKg,
                $beforeReps,
                $nowReps,
            );
        }

        return null;
    }

    /** Heaviest working set (ties broken by more reps); null when the exercise has none. */
    private function topWorkingSet(PerformedExercise $exercise): ?SetEntry
    {
        $best = null;
        foreach ($exercise->workingSets() as $set) {
            if ($best === null) {
                $best = $set;

                continue;
            }
            $bestWeight = $best->weightKg ?? 0.0;
            $setWeight = $set->weightKg ?? 0.0;
            if ($setWeight > $bestWeight || ($setWeight === $bestWeight && ($set->reps ?? 0) > ($best->reps ?? 0))) {
                $best = $set;
            }
        }

        return $best;
    }
}
