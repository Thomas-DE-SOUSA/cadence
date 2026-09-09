<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Strength\Domain\Port\NutritionEntryRepository;
use Cadence\Strength\Infrastructure\Read\NutritionView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShowNutritionController
{
    public function __construct(
        private readonly NutritionEntryRepository $entries,
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = isset($data['date']) ? (string) $data['date'] : $this->clock->now()->format('Y-m-d');
        $entries = $this->entries->forDate($this->tenantContext->current(), $date);

        return Inertia::render('MuscuNutrition', NutritionView::day($date, $entries));
    }
}
