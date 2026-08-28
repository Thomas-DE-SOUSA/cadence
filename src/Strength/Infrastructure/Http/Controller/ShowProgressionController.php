<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Service\OneRepMaxCalculator;
use Cadence\Strength\Infrastructure\Read\StrengthView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowProgressionController
{
    public function __construct(
        private readonly StrengthSessionRepository $sessions,
        private readonly OneRepMaxCalculator $oneRepMax,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(): Response
    {
        $tenant = $this->tenantContext->current();

        return Inertia::render('MuscuProgression', [
            'progression' => StrengthView::progression($this->sessions->forTenant($tenant, 300), $this->oneRepMax),
        ]);
    }
}
