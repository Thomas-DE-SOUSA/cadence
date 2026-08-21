<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Activity\Infrastructure\Read\HistoryView;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Training\Infrastructure\Read\WeekPlanView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowHistoryController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(): Response
    {
        $tenantId = $this->tenantContext->current()->value;
        $today = $this->clock->now();

        $activities = array_values(
            ActivityModel::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('occurred_at')
                ->get()
                ->all(),
        );

        // Planned session type for each day of the current Monday–Sunday week,
        // so the streak strip can show a deliberate rest day instead of a hole.
        $monday = $today->modify('-'.((int) $today->format('N') - 1).' days');
        $plannedByDate = WeekPlanView::typesByDate(
            $tenantId,
            $monday->format('Y-m-d'),
            $monday->modify('+6 days')->format('Y-m-d'),
        );

        return Inertia::render('History', HistoryView::build($activities, $today, $plannedByDate));
    }
}
