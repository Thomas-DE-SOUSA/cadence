<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Activity\Infrastructure\Read\ActivityView;
use Cadence\Shared\Application\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

final class ShowActivityController
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function __invoke(string $id): Response
    {
        $model = ActivityModel::query()
            ->where('tenant_id', $this->tenantContext->current()->value)
            ->where('id', $id)
            ->first();

        // Cross-tenant or unknown ids are indistinguishable: 404, never 403.
        abort_unless($model instanceof ActivityModel, 404);

        return Inertia::render('ActivityDetail', [
            'activity' => ActivityView::detail($model),
        ]);
    }
}
