<?php

declare(strict_types=1);

namespace Cadence\Athlete\Infrastructure\Http\Controller;

use Cadence\Athlete\Application\UseCase\SaveProfile\SaveProfileUseCase;
use Cadence\Athlete\Infrastructure\Http\Request\UpdateProfileRequest;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

final class UpdateProfileController
{
    public function __construct(
        private readonly SaveProfileUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(UpdateProfileRequest $request): RedirectResponse
    {
        $this->useCase->execute(
            $request->toInput(),
            new ExecutionContext($this->tenantContext->current()),
        );

        return redirect()->route('profile')->with('status', 'Profil enregistré.');
    }
}
