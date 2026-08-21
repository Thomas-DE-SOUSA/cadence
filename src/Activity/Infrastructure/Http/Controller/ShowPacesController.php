<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Activity\Infrastructure\Read\PaceView;
use Cadence\Shared\Application\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

final class ShowPacesController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
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

        return Inertia::render('Paces', PaceView::build($activities));
    }
}
