<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/** Everything the coach reasons from for one reply. */
final readonly class CoachContext
{
    public function __construct(
        public string $goal,
        public string $targetRaceName,
        public ?string $targetRaceDate,
        public ?FitnessSnapshot $fitness,
        public ?float $targetVdot,
        public string $recentSummary,
        public PlannedDay $day,
        public string $analysis = '',
    ) {
    }
}
