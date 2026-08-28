<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Service;

use Cadence\Strength\Domain\ValueObject\PerformedExercise;
use Cadence\Strength\Domain\ValueObject\SetEntry;

/**
 * Estimated 1-rep-max — the strength analogue of VDOT. Uses the Epley formula
 * (1RM = w · (1 + reps/30)), the de-facto standard, so we can track a lift's
 * progression across sessions on a single number regardless of the rep scheme.
 * Pure maths.
 */
final class OneRepMaxCalculator
{
    public function epley(float $weightKg, int $reps): float
    {
        if ($weightKg <= 0 || $reps <= 0) {
            return 0.0;
        }

        if ($reps === 1) {
            return $weightKg;
        }

        return $weightKg * (1 + $reps / 30);
    }

    public function forSet(SetEntry $set): float
    {
        if ($set->weightKg === null || $set->reps === null) {
            return 0.0;
        }

        return $this->epley($set->weightKg, $set->reps);
    }

    /** Best estimated 1RM across an exercise's working sets (0 when none loaded). */
    public function bestForExercise(PerformedExercise $exercise): float
    {
        $best = 0.0;
        foreach ($exercise->workingSets() as $set) {
            $best = max($best, $this->forSet($set));
        }

        return $best;
    }
}
