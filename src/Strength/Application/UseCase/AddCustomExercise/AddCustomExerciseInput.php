<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\AddCustomExercise;

final readonly class AddCustomExerciseInput
{
    public function __construct(
        public string $name,
        public string $primaryMuscle,
        public string $equipment,
    ) {
    }
}
