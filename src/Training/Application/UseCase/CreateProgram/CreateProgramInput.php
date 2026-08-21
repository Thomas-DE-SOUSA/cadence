<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\CreateProgram;

final readonly class CreateProgramInput
{
    /**
     * @param list<ObjectiveInput> $objectives
     */
    public function __construct(
        public string $name,
        public string $goal,
        public string $targetRaceName,
        public ?string $targetRaceDate,
        public string $startDate,
        public ?string $endDate,
        public string $priority,
        public array $objectives = [],
    ) {
    }
}
