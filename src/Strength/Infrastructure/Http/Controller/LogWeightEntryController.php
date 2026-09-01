<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Application\UseCase\LogWeightEntry\LogWeightEntryInput;
use Cadence\Strength\Application\UseCase\LogWeightEntry\LogWeightEntryUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LogWeightEntryController
{
    public function __construct(
        private readonly LogWeightEntryUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'moment' => ['required', 'in:MORNING,EVENING'],
            'weightKg' => ['required', 'numeric', 'between:20,400'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'weightKg.between' => 'Le poids doit être réaliste (entre 20 et 400 kg).',
            'weightKg.numeric' => 'Le poids doit être un nombre.',
        ]);

        $this->useCase->execute(
            new LogWeightEntryInput(
                isset($data['date']) ? (string) $data['date'] : null,
                (string) $data['moment'],
                (float) $data['weightKg'],
                (string) ($data['note'] ?? ''),
            ),
            new ExecutionContext($this->tenantContext->current()),
        );

        return redirect()->route('muscu.weight')->with('status', 'Poids enregistré.');
    }
}
