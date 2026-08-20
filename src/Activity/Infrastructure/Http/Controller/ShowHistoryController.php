<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Activity\Infrastructure\Read\ActivityView;
use Cadence\Shared\Application\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

final class ShowHistoryController
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function __invoke(): Response
    {
        $activities = ActivityModel::query()
            ->where('tenant_id', $this->tenantContext->current()->value)
            ->orderByDesc('occurred_at')
            ->get()
            ->map(static fn (ActivityModel $model): array => ActivityView::summary($model))
            ->all();

        return Inertia::render('History', [
            'activities' => array_values($activities),
        ]);
    }
}
