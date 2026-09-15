<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Domain\Port\ExerciseRepository;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Service\OneRepMaxCalculator;
use Cadence\Strength\Infrastructure\Read\StrengthView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowExerciseHistoryController
{
    public function __construct(
        private readonly StrengthSessionRepository $sessions,
        private readonly ExerciseRepository $exercises,
        private readonly OneRepMaxCalculator $oneRepMax,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(string $exerciseId): Response
    {
        $tenant = $this->tenantContext->current();

        $history = StrengthView::exerciseHistory($this->sessions->forTenant($tenant, 400), $exerciseId, $this->oneRepMax);

        // No done history yet → fall back to the catalog name for the header.
        if ($history['name'] === '') {
            foreach (StrengthView::catalog($this->exercises->forTenant($tenant)) as $entry) {
                if ($entry['id'] === $exerciseId) {
                    $history['name'] = $entry['name'];
                    break;
                }
            }
        }

        return Inertia::render('MuscuExerciseHistory', $history);
    }
}
