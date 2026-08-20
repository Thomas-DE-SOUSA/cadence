<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Activity\Infrastructure\Read\ActivityView;
use Cadence\Shared\Application\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

final class ShowDashboardController
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function __invoke(): Response
    {
        $model = ActivityModel::query()
            ->where('tenant_id', $this->tenantContext->current()->value)
            ->orderByDesc('occurred_at')
            ->first();

        return Inertia::render('Dashboard', [
            'activity' => $model instanceof ActivityModel ? ActivityView::detail($model) : null,
        ]);
    }
}
