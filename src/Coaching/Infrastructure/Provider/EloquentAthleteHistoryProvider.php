<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Provider;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Coaching\Application\FitnessAssessmentService;
use Cadence\Coaching\Domain\Port\AthleteHistoryProvider;
use Cadence\Coaching\Domain\ValueObject\PerformancePoint;
use Cadence\Coaching\Infrastructure\Read\AthleteBrief;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Domain\TenantId;

final class EloquentAthleteHistoryProvider implements AthleteHistoryProvider
{
    public function __construct(
        private readonly FitnessAssessmentService $fitness,
        private readonly Clock $clock,
    ) {
    }

    public function analysisFor(TenantId $tenant): string
    {
        return AthleteBrief::build($tenant->value, $this->fitness->forTenant($tenant), $this->clock->now()->format('Y-m-d'));
    }

    public function recentFor(TenantId $tenant): array
    {
        $points = [];
        foreach (
            ActivityModel::query()
                ->where('tenant_id', $tenant->value)
                ->orderByDesc('occurred_at')
                ->get() as $model
        ) {
            $points[] = new PerformancePoint(
                (int) $model->distance_meters,
                (int) $model->moving_seconds,
                (string) $model->occurred_at,
            );
        }

        return $points;
    }
}
