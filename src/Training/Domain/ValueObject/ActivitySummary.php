<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\ValueObject;

/**
 * A read-only snapshot of an activity, provided by the Activity context so the
 * Training context can evaluate objectives without depending on Activity internals.
 */
final class ActivitySummary
{
    /**
     * @param list<array{distanceMeters:int,durationSeconds:int}> $bestEfforts
     */
    public function __construct(
        public readonly string $activityId,
        public readonly string $occurredAtIso,
        public readonly int $distanceMeters,
        public readonly int $movingSeconds,
        public readonly float $averagePaceSecondsPerKm,
        public readonly array $bestEfforts = [],
    ) {
    }
}
