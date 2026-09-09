<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Application\UseCase\RemoveNutritionEntry\RemoveNutritionEntryUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DeleteNutritionEntryController
{
    public function __construct(
        private readonly RemoveNutritionEntryUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $this->useCase->execute($id, new ExecutionContext($this->tenantContext->current()));

        return redirect()->route('muscu.nutrition', isset($data['date']) ? ['date' => (string) $data['date']] : []);
    }
}
