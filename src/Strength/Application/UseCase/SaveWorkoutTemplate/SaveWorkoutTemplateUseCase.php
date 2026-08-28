<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\SaveWorkoutTemplate;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Strength\Domain\Model\WorkoutTemplate;
use Cadence\Strength\Domain\Port\WorkoutTemplateRepository;
use Cadence\Strength\Domain\ValueObject\PerformedExercise;

/** Creates or updates a reusable workout template for the acting tenant. */
final readonly class SaveWorkoutTemplateUseCase
{
    public function __construct(
        private WorkoutTemplateRepository $templates,
        private IdGenerator $ids,
    ) {
    }

    public function execute(SaveWorkoutTemplateInput $input, ExecutionContext $context): string
    {
        $id = $input->id ?? $this->ids->generate();

        $exercises = [];
        foreach ($input->exercises as $raw) {
            $exercises[] = PerformedExercise::fromArray($raw);
        }

        $existing = $input->id !== null ? $this->templates->ofId($input->id, $context->tenant) : null;
        $version = $existing !== null ? ($existing->toSnapshot()['version'] + 1) : 1;

        $this->templates->save(new WorkoutTemplate(
            $id,
            $context->tenant->value,
            trim($input->name),
            $exercises,
            $version,
        ));

        return $id;
    }
}
