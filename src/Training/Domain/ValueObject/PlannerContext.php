<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\ValueObject;

/** Everything the planner needs to design the next cycle. */
final class PlannerContext
{
    public function __construct(
        public readonly string $goal,
        public readonly string $targetRaceName,
        public readonly ?string $targetRaceDate,
        public readonly string $startDate,
        public readonly int $weeks,
        public readonly string $ressenti,
        public readonly string $recentPerformance,
        public readonly string $previousCycle,
        public readonly string $phaseName = '',
        public readonly string $phaseFocus = '',
        public readonly string $blueprint = '',
        public readonly string $athletePaces = '',
        public readonly string $athleteState = '',
    ) {
    }
}
