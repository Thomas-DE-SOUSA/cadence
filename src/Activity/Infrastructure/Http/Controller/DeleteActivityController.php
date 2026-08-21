<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Application\UseCase\DeleteActivity\DeleteActivityUseCase;
use Cadence\Activity\Domain\Exception\ActivityNotFound;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

final class DeleteActivityController
{
    public function __construct(
        private readonly DeleteActivityUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(string $id): RedirectResponse
    {
        try {
            $this->useCase->execute($id, new ExecutionContext($this->tenantContext->current()));
        } catch (ActivityNotFound) {
            abort(404);
        }

        return redirect()->route('dashboard')->with('status', 'Activité supprimée.');
    }
}
