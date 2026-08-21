<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Training\Application\UseCase\RegenerateCycle\RegenerateCycleUseCase;
use Cadence\Training\Domain\Exception\CycleNotFound;
use Cadence\Training\Infrastructure\Http\Request\RegenerateCycleRequest;
use Illuminate\Http\RedirectResponse;
use Throwable;

final class RegenerateCycleController
{
    public function __construct(
        private readonly RegenerateCycleUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(RegenerateCycleRequest $request, string $id, string $cycleId): RedirectResponse
    {
        try {
            $this->useCase->execute(
                $request->toInput($id, $cycleId),
                new ExecutionContext($this->tenantContext->current()),
            );
        } catch (CycleNotFound) {
            abort(404);
        } catch (Throwable $e) {
            return back()->withErrors(['ressenti' => $e->getMessage()]);
        }

        return redirect()->route('programs.show', $id)->with('status', 'Cycle refait par l\'IA.');
    }
}
