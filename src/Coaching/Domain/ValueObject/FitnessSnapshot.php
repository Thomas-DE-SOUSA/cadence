<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/**
 * A computed picture of the athlete's current fitness: VDOT, personalised
 * paces and recent training load. Derived from logged runs — no manual input.
 */
final readonly class FitnessSnapshot
{
    public function __construct(
        public float $vdot,
        public int $referenceDistanceMeters,
        public int $referenceSeconds,
        public string $referenceDate,
        public TrainingPaces $paces,
        public int $recentVolumeMeters,
        public int $longestRunMeters,
        public int $recentRunCount,
    ) {
    }
}
