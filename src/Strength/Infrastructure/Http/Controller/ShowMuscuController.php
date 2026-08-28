<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Domain\Port\ExerciseRepository;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Service\OneRepMaxCalculator;
use Cadence\Strength\Infrastructure\Read\StrengthView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowMuscuController
{
    public function __construct(
        private readonly StrengthSessionRepository $sessions,
        private readonly ExerciseRepository $exercises,
        private readonly OneRepMaxCalculator $oneRepMax,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(): Response
    {
        $tenant = $this->tenantContext->current();
        $sessions = $this->sessions->forTenant($tenant);

        return Inertia::render('Muscu', [
            'sessions' => StrengthView::summaries($sessions),
            'progression' => StrengthView::progression($sessions, $this->oneRepMax),
            'catalogCount' => count($this->exercises->forTenant($tenant)),
        ]);
    }
}
