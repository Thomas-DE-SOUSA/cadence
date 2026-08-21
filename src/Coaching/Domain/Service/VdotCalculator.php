<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Service;

use InvalidArgumentException;

/**
 * Daniels–Gilbert VDOT model. Estimates a runner's VDOT (a VO2max-equivalent
 * fitness score) from a race/effort, and derives training paces per intensity
 * zone. Pure maths — the public formulas, not any copyrighted material.
 */
final class VdotCalculator
{
    /** %VO2max intensities per training zone (Daniels E/M/T/I/R). */
    public const EASY = 0.70;

    public const MARATHON = 0.82;

    public const THRESHOLD = 0.86;

    public const INTERVAL = 0.97;

    public const REPETITION = 1.04;

    /** VDOT implied by covering a distance (m) in a time (s) at full effort. */
    public function vdot(int $distanceMeters, int $seconds): float
    {
        if ($distanceMeters <= 0 || $seconds <= 0) {
            throw new InvalidArgumentException('Distance and time must be positive.');
        }

        $minutes = $seconds / 60.0;
        $velocity = $distanceMeters / $minutes; // metres per minute

        $vo2 = -4.60 + 0.182258 * $velocity + 0.000104 * $velocity ** 2;
        $pctMax = 0.8
            + 0.1894393 * exp(-0.012778 * $minutes)
            + 0.2989558 * exp(-0.1932605 * $minutes);

        return $vo2 / $pctMax;
    }

    /** Training pace (seconds per km) for a VDOT at a given %VO2max intensity. */
    public function paceSecondsPerKm(float $vdot, float $intensity): int
    {
        $vo2 = $intensity * $vdot;

        // Invert vo2 = -4.60 + 0.182258·v + 0.000104·v²  for v (metres/minute).
        $a = 0.000104;
        $b = 0.182258;
        $c = -(4.60 + $vo2);
        $velocity = (-$b + sqrt($b * $b - 4 * $a * $c)) / (2 * $a);

        return (int) round(60000 / $velocity);
    }
}
