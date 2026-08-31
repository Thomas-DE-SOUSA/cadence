<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Port\WorkoutTemplateRepository;
use Cadence\Strength\Infrastructure\Read\StrengthView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowTemplatesController
{
    public function __construct(
        private readonly WorkoutTemplateRepository $templates,
        private readonly StrengthSessionRepository $sessions,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(): Response
    {
        $tenant = $this->tenantContext->current();

        return Inertia::render('MuscuTemplates', [
            'templates' => StrengthView::templates($this->templates->forTenant($tenant), $this->sessions->usageByTemplate($tenant)),
        ]);
    }
}
