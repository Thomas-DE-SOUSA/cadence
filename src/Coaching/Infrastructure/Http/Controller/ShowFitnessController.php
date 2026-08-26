<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Coaching\Application\FitnessAssessmentService;
use Cadence\Coaching\Domain\Service\AdaptationAnalyzer;
use Cadence\Coaching\Domain\Service\TrainingLoadCalculator;
use Cadence\Coaching\Infrastructure\Read\AdaptationView;
use Cadence\Coaching\Infrastructure\Read\TrainingLoadView;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Inertia\Inertia;
use Inertia\Response;

final class ShowFitnessController
{
    public function __construct(
        private readonly FitnessAssessmentService $fitness,
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(): Response
    {
        $tenant = $this->tenantContext->current();
        $snapshot = $this->fitness->forTenant($tenant);
        $today = $this->clock->now()->format('Y-m-d');

        $load = $snapshot === null
            ? ['hasData' => false]
            : TrainingLoadView::build($tenant->value, $snapshot, $today, new TrainingLoadCalculator());

        $adaptation = ($load['hasData'] ?? false) === true
            ? AdaptationView::build($tenant->value, $load, new AdaptationAnalyzer())
            : null;

        return Inertia::render('Forme', ['load' => $load, 'adaptation' => $adaptation]);
    }
}
