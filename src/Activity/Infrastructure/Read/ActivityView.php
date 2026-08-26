<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Read;

use Cadence\Activity\Domain\Service\GradeAdjustedPaceCalculator;
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

        $gapSegments = array_map(static fn (array $s): array => [
            'distanceMeters' => (int) $s['distance_meters'],
            'durationSeconds' => (int) $s['duration_seconds'],
            'elevationMeters' => (int) $s['elevation_meters'],
        ], $splits);

        return [
            ...self::summary($model),
            'elapsedSeconds' => $model->elapsed_seconds,
            'elevationGainMeters' => $model->elevation_gain_meters,
            'gapSecondsPerKm' => (new GradeAdjustedPaceCalculator())->overall($gapSegments),
            'splits' => array_map(static fn (array $s): array => [
                'index' => (int) $s['index'],
                'distanceMeters' => (int) $s['distance_meters'],
                'durationSeconds' => (int) $s['duration_seconds'],
                'elevationMeters' => (int) $s['elevation_meters'],
            ], $splits),
            'bestEfforts' => self::effortsFromSplits($splits),
            'track' => is_array($model->track) ? $model->track : null,
            'stream' => is_array($model->stream) ? $model->stream : null,
        ];
    }

    /**
     * This run's best effort per standard distance, from its km splits (fastest
     * rolling window). Reliable, unlike the imported best_efforts.
     *
     * @param list<array<string, mixed>> $splits
     *
     * @return list<array{label:string,distanceMeters:int,durationSeconds:int,isPersonalRecord:bool}>
     */
    private static function effortsFromSplits(array $splits): array
    {
        $durations = array_map(static fn (array $s): int => (int) $s['duration_seconds'], $splits);
        $distances = array_map(static fn (array $s): int => (int) $s['distance_meters'], $splits);
        $count = count($durations);

        $efforts = [];
        foreach ([1, 3, 5, 10, 15, 21] as $km) {
            if ($count < $km) {
                continue;
            }
            $bestDuration = null;
            $bestDistance = 0;
            for ($i = 0; $i + $km <= $count; $i++) {
                $windowDuration = 0;
                $windowDistance = 0;
                for ($j = 0; $j < $km; $j++) {
                    $windowDuration += $durations[$i + $j];
                    $windowDistance += $distances[$i + $j];
                }
                if ($windowDuration > 0 && ($bestDuration === null || $windowDuration < $bestDuration)) {
                    $bestDuration = $windowDuration;
                    $bestDistance = $windowDistance;
                }
            }
            if ($bestDuration !== null && $bestDistance > 0) {
                $nominal = $km * 1000;
                $efforts[] = [
                    'label' => $km.' km',
                    'distanceMeters' => $nominal,
                    'durationSeconds' => (int) round($bestDuration * $nominal / $bestDistance),
                    'isPersonalRecord' => false,
                ];
            }
        }

        return $efforts;
    }
}
