<?php

declare(strict_types=1);

namespace Cadence\Athlete\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Activity\Infrastructure\Read\PaceView;
use Cadence\Athlete\Domain\Port\AthleteRepository;
use Cadence\Athlete\Infrastructure\Read\ProfileView;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Training\Infrastructure\Read\RaceGoalView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowProfileController
{
    public function __construct(
        private readonly AthleteRepository $athletes,
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(): Response
    {
        $tenant = $this->tenantContext->current();

        $athlete = $this->athletes->ofTenant($tenant);

        $activities = array_values(
            ActivityModel::query()
                ->where('tenant_id', $tenant->value)
                ->orderByDesc('occurred_at')
                ->get()
                ->all(),
        );
        $pace = PaceView::build($activities);

        /** @var float|null $vdot */
        $vdot = $pace['vdot'];
        /** @var array{label:string}|null $basis */
        $basis = $pace['basis'];
        $basisLabel = is_array($basis) ? $basis['label'] : null;

        $programGoal = RaceGoalView::forTenant($tenant->value);

        return Inertia::render(
            'Profile',
            ProfileView::build($athlete, $vdot, $basisLabel, $programGoal, $this->clock->now()),
        );
    }
}
