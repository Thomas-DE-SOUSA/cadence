<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\SaveWorkoutTemplate;

final readonly class SaveWorkoutTemplateInput
{
    /** @param list<array<string, mixed>> $exercises PerformedExercise array shape */
    public function __construct(
        public ?string $id,
        public string $name,
        public array $exercises,
    ) {
    }
}
