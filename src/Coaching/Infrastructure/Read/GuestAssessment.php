<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Read;

use Cadence\Coaching\Domain\Service\VdotCalculator;

/**
 * Computes a runner's fitness picture from measured efforts (GPX splits and/or
 * manually entered race times): best effort per distance, an estimated VDOT and
 * Riegel projections across 5–21 km. Pure maths, no persistence — used by the
 * guest advisory ("Conseil") tool.
 */
final class GuestAssessment
{
    /** Nominal distances (m) we look for in splits. */
    private const EFFORT_DISTANCES_KM = [1, 3, 5, 10, 15, 20, 21];

    /** A km split slower than this (seconds/km) is treated as a stop / GPS artifact. */
    private const STOP_PACE = 780;

    /** Distances (m) we project a target time for. */
    private const PROJECTION_DISTANCES = [5000, 10000, 15000, 20000, 21097];

    /**
     * Best effort per nominal distance from per-km splits (sliding window).
     *
     * @param list<array{distanceMeters:int,durationSeconds:int}> $splits
     *
     * @return array<int, int> distanceMeters (nominal) => best seconds
     */
    public static function bestEffortsFromSplits(array $splits): array
    {
        $durations = [];
        $distances = [];
        foreach ($splits as $s) {
            $durations[] = $s['durationSeconds'];
            $distances[] = $s['distanceMeters'];
        }
        $n = count($durations);

        $best = [];
        foreach (self::EFFORT_DISTANCES_KM as $km) {
            if ($n < $km) {
                continue;
            }
            $nominal = $km * 1000;
            $bestDuration = null;
            $bestDistance = 0;
            for ($i = 0; $i + $km <= $n; $i++) {
                $wd = 0;
                $wdist = 0;
                $clean = true;
                for ($j = 0; $j < $km; $j++) {
                    $d = $durations[$i + $j];
                    $dist = $distances[$i + $j];
                    // A split slower than ~13 min/km is a stop / GPS drift, not a
                    // running effort — such a window can't be a genuine best effort.
                    if ($dist <= 0 || $d / ($dist / 1000) > self::STOP_PACE) {
                        $clean = false;
                        break;
                    }
                    $wd += $d;
                    $wdist += $dist;
                }
                // Genuine effort: clean splits + actually covers the nominal distance.
                if ($clean && $wd > 0 && $wdist >= $nominal * 0.995 && ($bestDuration === null || $wd < $bestDuration)) {
                    $bestDuration = $wd;
                    $bestDistance = $wdist;
                }
            }
            if ($bestDuration !== null && $bestDistance > 0) {
                $best[$nominal] = (int) round($bestDuration * $nominal / $bestDistance);
            }
        }

        return $best;
    }

    /**
     * @param array<int, int> $bestByDistance distanceMeters => seconds (keeps the fastest per distance)
     *
     * @return array{efforts:list<array{distanceMeters:int,label:string,seconds:int,paceSeconds:int}>,vdot:float|null,projections:list<array{distanceMeters:int,label:string,seconds:int,paceSeconds:int,measured:bool}>}
     */
    public static function assess(array $bestByDistance): array
    {
        ksort($bestByDistance);

        $efforts = [];
        foreach ($bestByDistance as $distance => $seconds) {
            $efforts[] = [
                'distanceMeters' => $distance,
                'label' => self::label($distance),
                'seconds' => $seconds,
                'paceSeconds' => (int) round($seconds / ($distance / 1000)),
            ];
        }

        $anchor = self::anchor($bestByDistance);
        $vdot = null;
        $projections = [];
        if ($anchor !== null) {
            $vdot = round((new VdotCalculator())->vdot($anchor['distance'], $anchor['seconds']), 1);
            foreach (self::PROJECTION_DISTANCES as $d) {
                $measured = isset($bestByDistance[$d]);
                $seconds = $measured
                    ? $bestByDistance[$d]
                    : (int) round($anchor['seconds'] * ($d / $anchor['distance']) ** 1.06);
                $projections[] = [
                    'distanceMeters' => $d,
                    'label' => self::label($d),
                    'seconds' => $seconds,
                    'paceSeconds' => (int) round($seconds / ($d / 1000)),
                    'measured' => $measured,
                ];
            }
        }

        return ['efforts' => $efforts, 'vdot' => $vdot, 'projections' => $projections];
    }

    /**
     * The most predictive effort to anchor projections on: prefer 10k, then 5k,
     * then the longest available.
     *
     * @param array<int, int> $bestByDistance
     *
     * @return array{distance:int,seconds:int}|null
     */
    private static function anchor(array $bestByDistance): ?array
    {
        foreach ([10000, 5000, 21097, 20000, 15000, 3000, 1000] as $preferred) {
            if (isset($bestByDistance[$preferred])) {
                return ['distance' => $preferred, 'seconds' => $bestByDistance[$preferred]];
            }
        }
        if ($bestByDistance === []) {
            return null;
        }
        $distance = (int) max(array_keys($bestByDistance));

        return ['distance' => $distance, 'seconds' => $bestByDistance[$distance]];
    }

    private static function label(int $meters): string
    {
        return match (true) {
            $meters === 21097, $meters === 21000 => 'Semi',
            $meters % 1000 === 0 => intdiv($meters, 1000).' km',
            default => number_format($meters / 1000, 1, ',', ' ').' km',
        };
    }
}
