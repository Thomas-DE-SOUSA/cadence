<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Activity\Infrastructure\Read\ProgressionView;
use Cadence\Athlete\Domain\Port\AthleteRepository;
use Cadence\Athlete\Infrastructure\Read\AthleteGoalView;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Training\Infrastructure\Read\RaceGoalView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowProgressionController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
        private readonly AthleteRepository $athletes,
    ) {
    }

    public function __invoke(): Response
    {
        $tenant = $this->tenantContext->current();
        $tenantId = $tenant->value;

        $activities = array_values(
            ActivityModel::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('occurred_at')
                ->get()
                ->all(),
        );

        // A goal set in the profile takes precedence over the program's.
        $goal = AthleteGoalView::forTenant($this->athletes, $tenant) ?? RaceGoalView::forTenant($tenantId);

        return Inertia::render('Progression', ProgressionView::build($activities, $this->clock->now(), $goal));
    }
}
