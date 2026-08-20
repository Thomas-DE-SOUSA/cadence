<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Shared\Application\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

final class ShowDashboardController
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function __invoke(): Response
    {
        $model = ActivityModel::query()
            ->where('tenant_id', $this->tenantContext->current()->value)
            ->orderByDesc('occurred_at')
            ->first();

        return Inertia::render('Dashboard', [
            'activity' => $model instanceof ActivityModel ? $this->toView($model) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function toView(ActivityModel $model): array
    {
        /** @var list<array<string, mixed>> $splits */
        $splits = $model->splits;
        /** @var list<array<string, mixed>> $efforts */
        $efforts = $model->best_efforts;

        return [
            'id' => $model->id,
            'occurredAt' => (string) $model->occurred_at,
            'source' => (string) $model->source,
            'distanceMeters' => $model->distance_meters,
            'movingSeconds' => $model->moving_seconds,
            'elapsedSeconds' => $model->elapsed_seconds,
            'elevationGainMeters' => $model->elevation_gain_meters,
            'averagePaceSecondsPerKm' => $model->average_pace_seconds_per_km,
            'splits' => array_map(static fn (array $s): array => [
                'index' => (int) $s['index'],
                'distanceMeters' => (int) $s['distance_meters'],
                'durationSeconds' => (int) $s['duration_seconds'],
                'elevationMeters' => (int) $s['elevation_meters'],
            ], $splits),
            'bestEfforts' => array_map(static fn (array $b): array => [
                'label' => (string) $b['label'],
                'distanceMeters' => (int) $b['distance_meters'],
                'durationSeconds' => (int) $b['duration_seconds'],
                'isPersonalRecord' => (bool) $b['is_personal_record'],
            ], $efforts),
        ];
    }
}
