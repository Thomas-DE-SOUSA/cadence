<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Read;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;

/**
 * Best efforts for standard distances, computed from the (reliable) km splits
 * with a sliding window — never slower than the full run. Falls back to the
 * stored best_efforts only when there are no splits. Shared by the dashboard
 * and the progression read models so records stay consistent everywhere.
 */
final class EffortCalculator
{
    private const DISTANCES_KM = [1, 3, 5, 10, 15, 20, 21, 30, 42];

    /**
     * @return list<array{distance:int,duration:int}>
     */
    public static function forActivity(ActivityModel $activity): array
    {
        /** @var mixed $rawSplits */
        $rawSplits = $activity->splits;
        $splits = array_values(array_filter(is_array($rawSplits) ? $rawSplits : [], 'is_array'));
        if ($splits === []) {
            return self::stored($activity);
        }

        $durations = [];
        $distances = [];
        foreach ($splits as $s) {
            $durations[] = (int) ($s['duration_seconds'] ?? 0);
            $distances[] = (int) ($s['distance_meters'] ?? 1000);
        }
        $n = count($durations);

        $efforts = [];
        foreach (self::DISTANCES_KM as $km) {
            if ($n < $km) {
                continue;
            }
            $bestDuration = null;
            $bestDistance = 0;
            for ($i = 0; $i + $km <= $n; $i++) {
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
                $efforts[] = ['distance' => $nominal, 'duration' => (int) round($bestDuration * $nominal / $bestDistance)];
            }
        }

        return $efforts;
    }

    /**
     * @return list<array{distance:int,duration:int}>
     */
    private static function stored(ActivityModel $activity): array
    {
        $efforts = [];
        /** @var mixed $raw */
        $raw = $activity->best_efforts;
        foreach (is_array($raw) ? $raw : [] as $e) {
            if (! is_array($e)) {
                continue;
            }
            $distance = (int) ($e['distance_meters'] ?? 0);
            $duration = (int) ($e['duration_seconds'] ?? 0);
            if ($distance > 0 && $duration > 0) {
                $efforts[] = ['distance' => $distance, 'duration' => $duration];
            }
        }

        return $efforts;
    }
}
