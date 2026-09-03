<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/** The athlete's current running objective, for framing the weekly review. */
final readonly class ProgramGoal
{
    public function __construct(
        public string $programName,
        public string $goal,
        public string $targetRaceName,
        public ?string $targetRaceDate,
    ) {
    }
}
