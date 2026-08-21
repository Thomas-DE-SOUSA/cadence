<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Training\Application\UseCase\CompleteCycle\CompleteCycleUseCase;
use Cadence\Training\Domain\Exception\CycleNotFound;
use Illuminate\Http\RedirectResponse;

final class CompleteCycleController
{
    public function __construct(
        private readonly CompleteCycleUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(string $id, string $cycleId): RedirectResponse
    {
        try {
            $this->useCase->execute($id, $cycleId, new ExecutionContext($this->tenantContext->current()));
        } catch (CycleNotFound) {
            abort(404);
        }

        return redirect()->route('programs.show', $id)->with('status', 'Cycle terminé — le suivant est débloqué.');
    }
}
