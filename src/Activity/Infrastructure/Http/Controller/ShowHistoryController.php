<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Activity\Infrastructure\Read\HistoryView;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
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
        $activities = array_values(
            ActivityModel::query()
                ->where('tenant_id', $this->tenantContext->current()->value)
                ->orderByDesc('occurred_at')
                ->get()
                ->all(),
        );

        return Inertia::render('History', HistoryView::build($activities, $this->clock->now()));
    }
}
