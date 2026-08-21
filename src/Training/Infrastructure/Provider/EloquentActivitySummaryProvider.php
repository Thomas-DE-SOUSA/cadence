<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Provider;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Domain\Port\ActivitySummaryProvider;
use Cadence\Training\Domain\ValueObject\ActivitySummary;

/** Reads the Activity context's data to feed program objective evaluation. */
final class EloquentActivitySummaryProvider implements ActivitySummaryProvider
{
    public function summariesFor(TenantId $tenant, array $activityIds): array
    {
        if ($activityIds === []) {
            return [];
        }

        $summaries = [];
        foreach (
            ActivityModel::query()
                ->where('tenant_id', $tenant->value)
                ->whereIn('id', $activityIds)
                ->get() as $model
        ) {
            $summaries[] = new ActivitySummary(
                $model->id,
                (string) $model->occurred_at,
                (int) $model->distance_meters,
                (int) $model->moving_seconds,
                (float) $model->average_pace_seconds_per_km,
                $this->bestEfforts($model),
            );
        }

        return $summaries;
    }

    /** @return list<array{distanceMeters:int,durationSeconds:int}> */
    private function bestEfforts(ActivityModel $model): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $model->best_efforts;

        return array_map(static fn (array $r): array => [
            'distanceMeters' => (int) $r['distance_meters'],
            'durationSeconds' => (int) $r['duration_seconds'],
        ], $rows);
    }
}
