<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Training\Application\UseCase\UnassignActivity\UnassignActivityUseCase;
use Cadence\Training\Domain\Exception\ProgramNotFound;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class UnassignActivityController
{
    public function __construct(
        private readonly UnassignActivityUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate(['activity_id' => ['required', 'string']]);

        try {
            $this->useCase->execute($id, (string) $validated['activity_id'], new ExecutionContext($this->tenantContext->current()));
        } catch (ProgramNotFound) {
            abort(404);
        }

        return back()->with('status', 'Sortie retirée du programme.');
    }
}
