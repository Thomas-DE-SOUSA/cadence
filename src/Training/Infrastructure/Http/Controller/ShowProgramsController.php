<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Training\Domain\Model\TrainingProgram;
use Cadence\Training\Domain\Port\ActivitySummaryProvider;
use Cadence\Training\Domain\Port\TrainingProgramRepository;
use Cadence\Training\Domain\ValueObject\ActivitySummary;
use Cadence\Training\Infrastructure\Read\ProgramView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowProgramsController
{
    public function __construct(
        private readonly TrainingProgramRepository $programs,
        private readonly ActivitySummaryProvider $summaries,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(): Response
    {
        $tenant = $this->tenantContext->current();
        $programs = $this->programs->allForTenant($tenant);

        $allIds = [];
        foreach ($programs as $program) {
            foreach ($program->assignedActivityIds() as $activityId) {
                $allIds[$activityId] = true;
            }
        }

        $byId = [];
        foreach ($this->summaries->summariesFor($tenant, array_keys($allIds)) as $summary) {
            $byId[$summary->activityId] = $summary;
        }

        $items = array_map(function (TrainingProgram $program) use ($byId): array {
            $subset = [];
            foreach ($program->assignedActivityIds() as $activityId) {
                if (isset($byId[$activityId])) {
                    $subset[] = $byId[$activityId];
                }
            }

            /** @var list<ActivitySummary> $subset */
            return ProgramView::listItem($program, $subset);
        }, $programs);

        return Inertia::render('Programs', ['programs' => $items]);
    }
}
