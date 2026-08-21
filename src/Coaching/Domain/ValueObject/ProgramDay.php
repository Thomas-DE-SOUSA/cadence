<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/** Read model: a program's goal context plus the day being discussed. */
final readonly class ProgramDay
{
    public function __construct(
        public string $programName,
        public string $goal,
        public string $targetRaceName,
        public ?string $targetRaceDate,
        public ?float $targetVdot,
        public PlannedDay $day,
    ) {
    }
}
