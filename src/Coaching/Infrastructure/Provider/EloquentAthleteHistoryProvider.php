<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Provider;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Coaching\Domain\Port\AthleteHistoryProvider;
use Cadence\Coaching\Domain\Service\FitnessAssessor;
use Cadence\Coaching\Domain\ValueObject\PerformancePoint;
use Cadence\Coaching\Infrastructure\Read\AthleteBrief;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Domain\TenantId;

final class EloquentAthleteHistoryProvider implements AthleteHistoryProvider
{
    // Depends on the pure FitnessAssessor, NOT FitnessAssessmentService: the
    // service depends on this provider, so injecting it would form a circular
    // dependency and blow the container's resolution stack.
    public function __construct(
        private readonly FitnessAssessor $assessor,
        private readonly Clock $clock,
    ) {
    }

    public function analysisFor(TenantId $tenant): string
    {
        $fitness = $this->assessor->assess($this->recentFor($tenant));

        return AthleteBrief::build($tenant->value, $fitness, $this->clock->now()->format('Y-m-d'));
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
