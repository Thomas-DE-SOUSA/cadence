<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Coaching\Application\FitnessAssessmentService;
use Cadence\Coaching\Domain\Service\VdotCalculator;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Domain\Enum\ObjectiveType;
use Cadence\Training\Domain\Model\Objective;
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
        private readonly FitnessAssessmentService $fitness,
        private readonly VdotCalculator $vdot,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $tenant = $this->tenantContext->current();
        $program = $this->programs->ofId(ProgramId::fromString($id), $tenant);

        abort_unless($program !== null, 404);

        $cycles = $this->cycles->forProgram($id, $tenant);

        $allActivities = ActivityModel::query()
            ->where('tenant_id', $tenant->value)
            ->orderByDesc('occurred_at')
            ->get();

        /** @var array<string, array<string, mixed>> $activityLookup keyed by id */
        $activityLookup = [];
        /** @var array<string, array<string, mixed>> $activityByDate keyed by Y-m-d */
        $activityByDate = [];
        foreach ($allActivities as $m) {
            $stats = [
                'id' => $m->id,
                'occurredAt' => (string) $m->occurred_at,
                'distanceMeters' => (int) $m->distance_meters,
                'movingSeconds' => (int) $m->moving_seconds,
                'averagePaceSecondsPerKm' => (float) $m->average_pace_seconds_per_km,
            ];
            $activityLookup[$m->id] = $stats;
            $day = substr((string) $m->occurred_at, 0, 10);
            $activityByDate[$day] ??= $stats;
        }

        // A run belongs to a day automatically when it falls on that date; a
        // manual link overrides the match. Both feed the coach evaluation.
        $planDates = [];
        $manualLinks = [];
        foreach ($cycles as $cycle) {
            foreach ($cycle->toSnapshot()['sessions'] as $session) {
                $planDates[$session['date']] = true;
                if ($session['activity_id'] !== null) {
                    $manualLinks[$session['activity_id']] = true;
                }
            }
        }

        $effectiveAssigned = $program->assignedActivityIds();
        foreach ($allActivities as $m) {
            if (isset($planDates[substr((string) $m->occurred_at, 0, 10)])) {
                $effectiveAssigned[] = $m->id;
            }
        }
        $summaries = $this->summaries->summariesFor($tenant, array_values(array_unique($effectiveAssigned)));

        // The pool holds runs not shown on any day: neither matching a plan date
        // nor manually linked.
        $available = $allActivities
            ->reject(fn (ActivityModel $m): bool => isset($planDates[substr((string) $m->occurred_at, 0, 10)]) || isset($manualLinks[$m->id]))
            ->map(fn (ActivityModel $m): array => [
                'id' => $m->id,
                'occurredAt' => (string) $m->occurred_at,
                'distanceMeters' => (int) $m->distance_meters,
            ])
            ->values()
            ->all();

        $planKey = $program->planKey();
        $latest = $cycles === [] ? null : $cycles[count($cycles) - 1];

        $nextPhaseExists = $planKey === null
            || (($plan = TrainingPlanCatalog::byKey($planKey)) !== null && $latest !== null && $plan->phase($latest->phaseIndex() + 1) !== null);
        $canGenerate = $latest === null || ($latest->isCompleted() && $nextPhaseExists);

        return Inertia::render('ProgramDetail', [
            'program' => ProgramView::detail($program, $summaries),
            'available' => $available,
            'cycles' => ProgramView::cycles($cycles, $activityLookup, $activityByDate),
            'roadmap' => ProgramView::roadmap($planKey, $cycles),
            'canGenerate' => $canGenerate,
            'activeCycleId' => $latest !== null && ! $latest->isCompleted() ? $latest->id()->value : null,
            'athlete' => $this->athleteProfile($tenant, $program->objectives()),
        ]);
    }

    /**
     * Computed runner profile (VDOT, personalised paces) plus the VDOT the goal
     * implies, so the UI can show the gap to the target.
     *
     * @param list<Objective> $objectives
     *
     * @return array<string, mixed>|null
     */
    private function athleteProfile(TenantId $tenant, array $objectives): ?array
    {
        $fitness = $this->fitness->forTenant($tenant);
        if ($fitness === null) {
            return null;
        }

        $target = null;
        foreach ($objectives as $objective) {
            if ($objective->type === ObjectiveType::RACE_TIME && $objective->targetDistanceMeters !== null && $objective->targetSeconds !== null) {
                $vdot = $this->vdot->vdot($objective->targetDistanceMeters, $objective->targetSeconds);
                if ($target === null || $vdot > $target['vdot']) {
                    $target = [
                        'vdot' => round($vdot, 1),
                        'distanceMeters' => $objective->targetDistanceMeters,
                        'seconds' => $objective->targetSeconds,
                    ];
                }
            }
        }

        return [
            'vdot' => $fitness->vdot,
            'reference' => [
                'distanceMeters' => $fitness->referenceDistanceMeters,
                'seconds' => $fitness->referenceSeconds,
                'date' => $fitness->referenceDate,
            ],
            'paces' => $fitness->paces->toArray(),
            'recentVolumeMeters' => $fitness->recentVolumeMeters,
            'longestRunMeters' => $fitness->longestRunMeters,
            'recentRunCount' => $fitness->recentRunCount,
            'target' => $target,
        ];
    }
}
