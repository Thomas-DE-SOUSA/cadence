<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Strength\Domain\Port\WeightEntryRepository;
use Cadence\Strength\Infrastructure\Read\WeightView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowWeightController
{
    public function __construct(
        private readonly WeightEntryRepository $entries,
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(): Response
    {
        $tenant = $this->tenantContext->current();
        $now = $this->clock->now();
        $since = $now->modify('-120 days')->format('Y-m-d');

        $entries = $this->entries->since($tenant, $since);

        return Inertia::render('MuscuWeight', [
            'today' => $now->format('Y-m-d'),
            'weeks' => WeightView::weeklyAverages($entries),
            'recent' => WeightView::recent($entries),
        ]);
    }
}
