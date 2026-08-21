<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Training\Application\UseCase\AssignSessionActivity\AssignSessionActivityUseCase;
use Cadence\Training\Domain\Exception\CycleNotFound;
use Cadence\Training\Infrastructure\Http\Request\AssignSessionActivityRequest;
use Illuminate\Http\RedirectResponse;

final class AssignSessionActivityController
{
    public function __construct(
        private readonly AssignSessionActivityUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(AssignSessionActivityRequest $request, string $id, string $cycleId): RedirectResponse
    {
        try {
            $this->useCase->execute(
                $id,
                $cycleId,
                $request->sessionDate(),
                $request->activityId(),
                new ExecutionContext($this->tenantContext->current()),
            );
        } catch (CycleNotFound) {
            abort(404);
        }

        return back(fallback: route('programs.show', $id));
    }
}
