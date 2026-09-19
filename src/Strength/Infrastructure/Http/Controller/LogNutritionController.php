<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Application\UseCase\LogFood\LogFoodInput;
use Cadence\Strength\Application\UseCase\LogFood\LogPendingFoodUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LogNutritionController
{
    public function __construct(
        private readonly LogPendingFoodUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:500'],
            'meal' => ['required', 'in:matin,midi,soir'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'text.required' => "Décris ce que tu as mangé.",
            'text.max' => 'Trop long — décris un repas à la fois.',
        ]);

        $date = isset($data['date']) ? (string) $data['date'] : null;

        // Save instantly as "pending"; the client then triggers the AI estimation
        // (strength.nutrition.estimate), so this request never waits on Gemini.
        $this->useCase->execute(
            new LogFoodInput((string) $data['text'], (string) $data['meal'], $date),
            new ExecutionContext($this->tenantContext->current()),
        );

        return redirect()->route('strength.nutrition', $date !== null ? ['date' => $date] : []);
    }
}
