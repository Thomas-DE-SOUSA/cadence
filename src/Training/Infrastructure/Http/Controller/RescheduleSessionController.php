<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Training\Application\UseCase\RescheduleSession\RescheduleSessionUseCase;
use Cadence\Training\Domain\Exception\CycleNotFound;
use Cadence\Training\Infrastructure\Http\Request\RescheduleSessionRequest;
use Illuminate\Http\RedirectResponse;

final class RescheduleSessionController
{
    public function __construct(
        private readonly RescheduleSessionUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(RescheduleSessionRequest $request, string $id, string $cycleId): RedirectResponse
    {
        try {
            $this->useCase->execute(
                $id,
                $cycleId,
                $request->fromDate(),
                $request->toDate(),
                new ExecutionContext($this->tenantContext->current()),
            );
        } catch (CycleNotFound) {
            abort(404);
        }

        return back(fallback: route('programs.show', $id));
    }
}
