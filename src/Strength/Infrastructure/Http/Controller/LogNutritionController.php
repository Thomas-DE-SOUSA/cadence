<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Application\Port\Exception\FoodEstimationFailed;
use Cadence\Strength\Application\UseCase\LogFood\LogFoodInput;
use Cadence\Strength\Application\UseCase\LogFood\LogFoodUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LogNutritionController
{
    public function __construct(
        private readonly LogFoodUseCase $useCase,
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
        $back = redirect()->route('muscu.nutrition', $date !== null ? ['date' => $date] : []);

        try {
            $saved = $this->useCase->execute(
                new LogFoodInput((string) $data['text'], (string) $data['meal'], $date),
                new ExecutionContext($this->tenantContext->current()),
            );
        } catch (FoodEstimationFailed) {
            return $back->with('error', "L'estimation IA a échoué. Réessaie dans un instant.");
        }

        if ($saved === []) {
            return $back->with('error', "Je n'ai rien reconnu à estimer. Reformule un peu.");
        }

        return $back->with('status', count($saved).' aliment(s) ajouté(s).');
    }
}
