<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Illuminate\Http\RedirectResponse;

final class RemoveScheduledWorkoutController
{
    public function __construct(
        private readonly StrengthSessionRepository $sessions,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(string $id): RedirectResponse
    {
        $this->sessions->delete($id, $this->tenantContext->current());

        return redirect()->route('muscu')->with('status', 'Séance retirée de l\'agenda.');
    }
}
