<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Application\UseCase\AddCustomExercise\AddCustomExerciseInput;
use Cadence\Strength\Application\UseCase\AddCustomExercise\AddCustomExerciseUseCase;
use Cadence\Strength\Domain\Enum\Equipment;
use Cadence\Strength\Domain\Enum\MuscleGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AddCustomExerciseController
{
    public function __construct(
        private readonly AddCustomExerciseUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'primaryMuscle' => ['required', Rule::in(array_map(static fn (MuscleGroup $m): string => $m->value, MuscleGroup::cases()))],
            'equipment' => ['required', Rule::in(array_map(static fn (Equipment $e): string => $e->value, Equipment::cases()))],
        ]);

        $this->useCase->execute(
            new AddCustomExerciseInput((string) $data['name'], (string) $data['primaryMuscle'], (string) $data['equipment']),
            new ExecutionContext($this->tenantContext->current()),
        );

        return back()->with('status', 'Exercice ajouté à ta bibliothèque.');
    }
}
