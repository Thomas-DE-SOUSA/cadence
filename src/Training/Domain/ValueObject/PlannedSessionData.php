<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\ValueObject;

/** A session as produced by the planner: day offset from the cycle start + targets. */
final class PlannedSessionData
{
    /**
     * @param list<SessionStep> $steps
     */
    public function __construct(
        public readonly int $dayOffset,
        public readonly string $type,
        public readonly string $title,
        public readonly string $description,
        public readonly ?int $targetDistanceMeters = null,
        public readonly ?int $targetDurationSeconds = null,
        public readonly ?int $targetPaceSecondsPerKm = null,
        public readonly array $steps = [],
    ) {
    }
}
