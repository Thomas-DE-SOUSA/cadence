<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Application\UseCase\SaveWorkoutTemplate\SaveWorkoutTemplateInput;
use Cadence\Strength\Application\UseCase\SaveWorkoutTemplate\SaveWorkoutTemplateUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SaveTemplateController
{
    public function __construct(
        private readonly SaveWorkoutTemplateUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'exercises' => ['array'],
            'exercises.*.exercise_id' => ['required', 'string'],
            'exercises.*.name' => ['required', 'string', 'max:160'],
            'exercises.*.note' => ['nullable', 'string', 'max:280'],
            'exercises.*.per_side' => ['nullable', 'boolean'],
            'exercises.*.superset_group' => ['nullable', 'integer'],
            'exercises.*.sets' => ['array'],
            'exercises.*.sets.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'exercises.*.sets.*.reps' => ['nullable', 'integer', 'min:0'],
            'exercises.*.sets.*.rpe' => ['nullable', 'numeric', 'between:0,10'],
            'exercises.*.sets.*.duration_seconds' => ['nullable', 'integer', 'min:0'],
            'exercises.*.sets.*.is_warmup' => ['nullable', 'boolean'],
            'exercises.*.sets.*.done' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Donne un nom à ta séance.',
            'exercises.*.sets.*.rpe.between' => 'Le RPE doit être entre 0 et 10 (laisse vide si tu ne l’utilises pas).',
            'exercises.*.sets.*.rpe.numeric' => 'Le RPE doit être un nombre.',
            'exercises.*.sets.*.weight_kg.numeric' => 'Le poids doit être un nombre.',
            'exercises.*.sets.*.reps.integer' => 'Les répétitions doivent être un nombre entier.',
        ]);

        /** @var list<array<string, mixed>> $exercises */
        $exercises = array_values($data['exercises'] ?? []);

        $this->useCase->execute(
            new SaveWorkoutTemplateInput(isset($data['id']) ? (string) $data['id'] : null, (string) $data['name'], $exercises),
            new ExecutionContext($this->tenantContext->current()),
        );

        return redirect()->route('muscu.templates')->with('status', 'Séance enregistrée 💪');
    }
}
