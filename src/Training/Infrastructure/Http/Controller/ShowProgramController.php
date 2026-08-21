<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Shared\Application\TenantContext;
use Cadence\Training\Domain\Plan\TrainingPlanCatalog;
use Cadence\Training\Domain\Port\ActivitySummaryProvider;
use Cadence\Training\Domain\Port\CycleRepository;
use Cadence\Training\Domain\Port\TrainingProgramRepository;
use Cadence\Training\Domain\ValueObject\ProgramId;
use Cadence\Training\Infrastructure\Read\ProgramView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowProgramController
{
    public function __construct(
        private readonly TrainingProgramRepository $programs,
        private readonly ActivitySummaryProvider $summaries,
        private readonly CycleRepository $cycles,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $tenant = $this->tenantContext->current();
        $program = $this->programs->ofId(ProgramId::fromString($id), $tenant);

        abort_unless($program !== null, 404);

        $summaries = $this->summaries->summariesFor($tenant, $program->assignedActivityIds());
        $assigned = array_flip($program->assignedActivityIds());

        $available = ActivityModel::query()
            ->where('tenant_id', $tenant->value)
            ->orderByDesc('occurred_at')
            ->get()
            ->reject(fn (ActivityModel $m): bool => isset($assigned[$m->id]))
            ->map(fn (ActivityModel $m): array => [
                'id' => $m->id,
                'occurredAt' => (string) $m->occurred_at,
                'distanceMeters' => (int) $m->distance_meters,
            ])
            ->values()
            ->all();

        $cycles = $this->cycles->forProgram($id, $tenant);
        $planKey = $program->planKey();
        $latest = $cycles === [] ? null : $cycles[count($cycles) - 1];

        $nextPhaseExists = $planKey === null
            || (($plan = TrainingPlanCatalog::byKey($planKey)) !== null && $latest !== null && $plan->phase($latest->phaseIndex() + 1) !== null);
        $canGenerate = $latest === null || ($latest->isCompleted() && $nextPhaseExists);

        return Inertia::render('ProgramDetail', [
            'program' => ProgramView::detail($program, $summaries),
            'available' => $available,
            'cycles' => ProgramView::cycles($cycles),
            'roadmap' => ProgramView::roadmap($planKey, $cycles),
            'canGenerate' => $canGenerate,
            'activeCycleId' => $latest !== null && ! $latest->isCompleted() ? $latest->id()->value : null,
        ]);
    }
}
