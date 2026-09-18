<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Application\UseCase\LogFood\EstimateFoodUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Runs the deferred AI estimation of a pending nutrition entry. Triggered by the
 * client right after a pending entry is created, so the initial log stays
 * instant. Estimation errors are swallowed into a "failed" status by the use
 * case — this endpoint always redirects back cleanly.
 */
final class EstimateNutritionController
{
    public function __construct(
        private readonly EstimateFoodUseCase $useCase,
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
