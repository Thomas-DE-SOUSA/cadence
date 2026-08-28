<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Domain\Port\WorkoutTemplateRepository;
use Illuminate\Http\RedirectResponse;

final class DeleteTemplateController
{
    public function __construct(
        private readonly WorkoutTemplateRepository $templates,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(string $id): RedirectResponse
    {
        $this->templates->delete($id, $this->tenantContext->current());

        return redirect()->route('muscu.templates')->with('status', 'Séance supprimée.');
    }
}
