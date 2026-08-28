<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\AddCustomExercise;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Strength\Domain\Enum\Equipment;
use Cadence\Strength\Domain\Enum\MuscleGroup;
use Cadence\Strength\Domain\Model\Exercise;
use Cadence\Strength\Domain\Port\ExerciseRepository;

/** Adds an exercise the library doesn't have yet, owned by the acting tenant. */
final readonly class AddCustomExerciseUseCase
{
    public function __construct(
        private ExerciseRepository $exercises,
        private IdGenerator $ids,
    ) {
    }

    public function execute(AddCustomExerciseInput $input, ExecutionContext $context): string
    {
        $id = $this->ids->generate();

        $this->exercises->save(new Exercise(
            $id,
            $context->tenant->value,
            trim($input->name),
            MuscleGroup::from($input->primaryMuscle),
            Equipment::from($input->equipment),
            isCustom: true,
        ));

        return $id;
    }
}
