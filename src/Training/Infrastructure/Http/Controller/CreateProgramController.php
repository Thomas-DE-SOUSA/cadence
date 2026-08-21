<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Training\Application\UseCase\CreateProgram\CreateProgramUseCase;
use Cadence\Training\Infrastructure\Http\Request\CreateProgramRequest;
use Illuminate\Http\RedirectResponse;

final class CreateProgramController
{
    public function __construct(
        private readonly CreateProgramUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(CreateProgramRequest $request): RedirectResponse
    {
        $programId = $this->useCase->execute(
            $request->toInput(),
            new ExecutionContext($this->tenantContext->current()),
        );

        return redirect()->route('programs.show', $programId)->with('status', 'Programme créé.');
    }
}
