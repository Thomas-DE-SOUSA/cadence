<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Strength\Infrastructure\Read\NutritionView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowNutritionController
{
    public function __invoke(): Response
    {
        return Inertia::render('MuscuNutrition', NutritionView::leanBulkPlan());
    }
}
