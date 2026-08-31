<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Strength\Domain\Port\ExerciseRepository;
use Cadence\Strength\Domain\Port\MuscuProfileRepository;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Service\OneRepMaxCalculator;
use Cadence\Strength\Infrastructure\Read\StrengthView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowProgressionController
{
    public function __construct(
        private readonly StrengthSessionRepository $sessions,
        private readonly ExerciseRepository $exercises,
        private readonly MuscuProfileRepository $profiles,
        private readonly OneRepMaxCalculator $oneRepMax,
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(): Response
    {
        $tenant = $this->tenantContext->current();
        $today = $this->clock->now()->format('Y-m-d');

        $sessions = $this->sessions->forTenant($tenant, 400);
        $profile = $this->profiles->forTenant($tenant);

        return Inertia::render('MuscuProgression', [
            'goal' => $profile?->goal()->value ?? 'GENERAL',
            'hasProfile' => $profile !== null,
            'weekly' => StrengthView::weekly($sessions, $today),
            'muscleVolume' => StrengthView::volumeByMuscle($sessions, $this->exercises->forTenant($tenant), $today),
            'records' => StrengthView::records($sessions, $this->oneRepMax, $today),
            'progression' => StrengthView::progression($sessions, $this->oneRepMax),
        ]);
    }
}
