<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\Service;

/**
 * Grade-adjusted pace (GAP): normalises running pace for hills using Minetti's
 * energy cost of running on a gradient. It answers "what flat pace would this
 * effort be worth?" — essential once trails and D+ enter the picture.
 */
final class GradeAdjustedPaceCalculator
{
    /** Energy cost of running on the flat (J/kg/m), Minetti 2002. */
    private const FLAT_COST = 3.6;

    /** Energy cost at a signed gradient (rise/run), clamped to a sane ±30%. */
    private function cost(float $grade): float
    {
        $i = max(-0.30, min(0.30, $grade));

        return 155.4 * $i ** 5 - 30.4 * $i ** 4 - 43.3 * $i ** 3 + 46.3 * $i ** 2 + 19.5 * $i + 3.6;
    }

    /** Cost relative to the flat (1.0 on the flat, > 1 uphill). */
    public function costRatio(float $grade): float
    {
        return $this->cost($grade) / self::FLAT_COST;
    }

    /** Flat-equivalent pace for a segment run at $actualPace on $grade. */
    public function adjustedPace(float $actualPaceSecondsPerKm, float $grade): float
    {
        if ($actualPaceSecondsPerKm <= 0.0) {
            return 0.0;
        }

        return $actualPaceSecondsPerKm / $this->costRatio($grade);
    }

    /**
     * Overall GAP (seconds/km) across segments, weighted by distance. Null when
     * there is nothing to compute.
     *
     * @param list<array{distanceMeters: int|float, durationSeconds: int, elevationMeters: int|float}> $segments
     */
    public function overall(array $segments): ?int
    {
        $gapSeconds = 0.0;
        $km = 0.0;

        foreach ($segments as $s) {
            $dist = (float) $s['distanceMeters'];
            $dur = (int) $s['durationSeconds'];
            if ($dist <= 0.0 || $dur <= 0) {
                continue;
            }
            $km += $dist / 1000;
            $pace = $dur / ($dist / 1000);
            $gapSeconds += ($dist / 1000) * $this->adjustedPace($pace, (float) $s['elevationMeters'] / $dist);
        }

        return $km > 0.0 ? (int) round($gapSeconds / $km) : null;
    }
}
