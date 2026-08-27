<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Coaching\Application\FitnessAssessmentService;
use Cadence\Coaching\Domain\Port\WellnessCheckInRepository;
use Cadence\Coaching\Domain\Service\AdaptationAnalyzer;
use Cadence\Coaching\Domain\Service\ReadinessAssessor;
use Cadence\Coaching\Domain\Service\TrainingLoadCalculator;
use Cadence\Coaching\Domain\ValueObject\WellnessCheckIn;
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
        private readonly WellnessCheckInRepository $checkIns,
        private readonly ReadinessAssessor $readiness,
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
            ? AdaptationView::build($tenant->value, $load, $today, new AdaptationAnalyzer())
            : null;

        $todayCheckIn = $this->checkIns->forDate($tenant, $today);

        return Inertia::render('Forme', [
            'load' => $load,
            'adaptation' => $adaptation,
            'checkin' => $todayCheckIn !== null ? $this->present($todayCheckIn) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function present(WellnessCheckIn $c): array
    {
        $r = $this->readiness->assess($c);

        return [
            'sleep' => $c->sleep,
            'energy' => $c->energy,
            'legs' => $c->legs,
            'motivation' => $c->motivation,
            'painLevel' => $c->painLevel,
            'painLocation' => $c->painLocation,
            'note' => $c->note,
            'readiness' => [
                'score' => $r->score,
                'level' => $r->level->value,
                'label' => $r->level->label(),
                'headline' => $r->headline,
                'advice' => $r->advice,
            ],
        ];
    }
}
