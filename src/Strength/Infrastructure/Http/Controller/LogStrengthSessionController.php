<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Application\UseCase\LogStrengthSession\LogStrengthSessionInput;
use Cadence\Strength\Application\UseCase\LogStrengthSession\LogStrengthSessionUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LogStrengthSessionController
{
    public function __construct(
        private readonly LogStrengthSessionUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
            'durationSeconds' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:PLANNED,DONE'],
            'templateId' => ['nullable', 'string'],
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
            'exercises.*.sets.*.rpe.between' => 'Le RPE doit être entre 0 et 10 (laisse vide si tu ne l’utilises pas).',
            'exercises.*.sets.*.rpe.numeric' => 'Le RPE doit être un nombre.',
            'exercises.*.sets.*.weight_kg.numeric' => 'Le poids doit être un nombre.',
            'exercises.*.sets.*.reps.integer' => 'Les répétitions doivent être un nombre entier.',
        ]);

        /** @var list<array<string, mixed>> $exercises */
        $exercises = array_values($data['exercises'] ?? []);

        $this->useCase->execute(
            new LogStrengthSessionInput(
                isset($data['id']) ? (string) $data['id'] : null,
                (string) ($data['date'] ?? ''),
                (string) ($data['title'] ?? ''),
                (string) ($data['note'] ?? ''),
                isset($data['durationSeconds']) ? (int) $data['durationSeconds'] : null,
                $exercises,
                (string) ($data['status'] ?? 'DONE'),
                isset($data['templateId']) ? (string) $data['templateId'] : null,
            ),
            new ExecutionContext($this->tenantContext->current()),
        );

        return redirect()->route('muscu')->with('status', 'Séance enregistrée 💪');
    }
}
