<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Read;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;

/**
 * Maps Eloquent read rows to view-model arrays for Inertia. Read side only —
 * it bypasses the aggregate on purpose (CQRS query path).
 */
final class ActivityView
{
    /** @return array{id:string,occurredAt:string,source:string,distanceMeters:int,movingSeconds:int,averagePaceSecondsPerKm:float} */
    public static function summary(ActivityModel $model): array
    {
        return [
            'id' => $model->id,
            'occurredAt' => (string) $model->occurred_at,
            'source' => (string) $model->source,
            'distanceMeters' => $model->distance_meters,
            'movingSeconds' => $model->moving_seconds,
            'averagePaceSecondsPerKm' => $model->average_pace_seconds_per_km,
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(ActivityModel $model): array
    {
        /** @var list<array<string, mixed>> $splits */
        $splits = $model->splits;
        /** @var list<array<string, mixed>> $efforts */
        $efforts = $model->best_efforts;

        return [
            ...self::summary($model),
            'elapsedSeconds' => $model->elapsed_seconds,
            'elevationGainMeters' => $model->elevation_gain_meters,
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
