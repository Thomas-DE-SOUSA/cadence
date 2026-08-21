<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Read;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Coaching\Domain\Service\VdotCalculator;

/**
 * Builds the training-pace screen: the athlete's current VDOT (from the best
 * real effort) and the five Daniels pace zones derived from it, plus equivalent
 * race paces per distance. Recalibrates automatically as efforts improve.
 */
final class PaceView
{
    /** Zone key => [label intensity high (faster), intensity low (slower)]; equal = single pace. */
    private const ZONES = [
        'recovery' => [0.66, 0.62],
        'easy' => [0.74, 0.68],
        'marathon' => [VdotCalculator::MARATHON, VdotCalculator::MARATHON],
        'threshold' => [VdotCalculator::THRESHOLD, VdotCalculator::THRESHOLD],
        'interval' => [VdotCalculator::INTERVAL, VdotCalculator::INTERVAL],
        'repetition' => [VdotCalculator::REPETITION, VdotCalculator::REPETITION],
    ];

    /** Distances (m) to show equivalent race paces for. */
    private const RACE_DISTANCES = [5000, 10000, 21097, 42195];

    /**
     * @param list<ActivityModel> $models most recent first
     *
     * @return array<string, mixed>
     */
    public static function build(array $models): array
    {
        $basis = self::basis($models);
        if ($basis === null) {
            return ['vdot' => null, 'basis' => null, 'zones' => [], 'racePaces' => []];
        }

        $calc = new VdotCalculator();
        $vdot = $calc->vdot($basis['distanceMeters'], $basis['seconds']);

        return [
            'vdot' => round($vdot, 1),
            'basis' => $basis,
            'zones' => self::zones($calc, $vdot),
            'racePaces' => self::racePaces($basis),
        ];
    }

    /**
     * The best effort to base fitness on: prefer 10k, then 5k, then longer/shorter.
     *
     * @param list<ActivityModel> $models
     *
     * @return array{distanceMeters:int,seconds:int,label:string}|null
     */
    private static function basis(array $models): ?array
    {
        /** @var array<int, int> $bests distance => best duration */
        $bests = [];
        foreach ($models as $m) {
            foreach (EffortCalculator::forActivity($m) as $e) {
                if (! isset($bests[$e['distance']]) || $e['duration'] < $bests[$e['distance']]) {
                    $bests[$e['distance']] = $e['duration'];
                }
            }
        }
        if ($bests === []) {
            return null;
        }

        $distance = null;
        foreach ([10000, 5000, 21097, 3000, 1609, 1000] as $preferred) {
            if (isset($bests[$preferred])) {
                $distance = $preferred;
                break;
            }
        }
        if ($distance === null) {
            $distance = max(array_keys($bests)); // longest available
        }

        return [
            'distanceMeters' => $distance,
            'seconds' => $bests[$distance],
            'label' => self::distanceLabel($distance),
        ];
    }

    /**
     * @return list<array{key:string,minSeconds:int,maxSeconds:int}>
     */
    private static function zones(VdotCalculator $calc, float $vdot): array
    {
        $zones = [];
        foreach (self::ZONES as $key => [$fast, $slow]) {
            // Higher %VO2max = faster = fewer seconds/km.
            $fastPace = $calc->paceSecondsPerKm($vdot, $fast);
            $slowPace = $calc->paceSecondsPerKm($vdot, $slow);
            $zones[] = [
                'key' => $key,
                'minSeconds' => min($fastPace, $slowPace),
                'maxSeconds' => max($fastPace, $slowPace),
            ];
        }

        return $zones;
    }

    /**
     * Equivalent race paces at standard distances, via Riegel from the basis
     * effort: T2 = T1 · (D2 / D1) ^ 1.06.
     *
     * @param array{distanceMeters:int,seconds:int,label:string} $basis
     *
     * @return list<array{label:string,distanceMeters:int,seconds:int,paceSeconds:int,isBasis:bool}>
     */
    private static function racePaces(array $basis): array
    {
        $out = [];
        foreach (self::RACE_DISTANCES as $distance) {
            $isBasis = $distance === $basis['distanceMeters'];
            $seconds = $isBasis
                ? $basis['seconds']
                : (int) round($basis['seconds'] * ($distance / $basis['distanceMeters']) ** 1.06);
            $out[] = [
                'label' => self::distanceLabel($distance),
                'distanceMeters' => $distance,
                'seconds' => $seconds,
                'paceSeconds' => (int) round($seconds / ($distance / 1000)),
                'isBasis' => $isBasis,
            ];
        }

        return $out;
    }

    private static function distanceLabel(int $meters): string
    {
        return match (true) {
            $meters === 42195, $meters === 42000 => 'Marathon',
            $meters === 21097, $meters === 21000 => 'Semi',
            $meters === 3219 => '2 miles',
            $meters === 1609 => '1 mile',
            $meters % 1000 === 0 => intdiv($meters, 1000).' km',
            default => number_format($meters / 1000, 2, ',', ' ').' km',
        };
    }
}
