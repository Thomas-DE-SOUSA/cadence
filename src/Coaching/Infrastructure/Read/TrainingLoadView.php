<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Read;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Coaching\Domain\Service\TrainingLoadCalculator;
use Cadence\Coaching\Domain\ValueObject\FitnessSnapshot;
use DateTimeImmutable;

/** Builds the "Forme & charge" page payload from the athlete's runs. */
final class TrainingLoadView
{
    /** @return array<string, mixed> */
    public static function build(string $tenantId, FitnessSnapshot $fitness, string $today, TrainingLoadCalculator $calc): array
    {
        $threshold = $fitness->paces->threshold;
        $marathon = $fitness->paces->marathon;

        /** @var iterable<ActivityModel> $activities */
        $activities = ActivityModel::query()->where('tenant_id', $tenantId)->orderBy('occurred_at')->get();

        /** @var array<string, float> $daily */
        $daily = [];
        $first = null;
        foreach ($activities as $a) {
            $date = substr((string) $a->occurred_at, 0, 10);
            $first ??= $date;
            $daily[$date] = ($daily[$date] ?? 0.0)
                + $calc->stress((int) $a->moving_seconds, (float) $a->average_pace_seconds_per_km, $threshold);
        }

        if ($first === null) {
            return ['hasData' => false];
        }

        $series = $calc->formSeries($daily, $first, $today);
        $series = array_slice($series, -84); // keep ~12 weeks on the chart
        /** @var array{date:string,ctl:float,atl:float,tsb:float} $last */
        $last = end($series) ?: ['ctl' => 0.0, 'atl' => 0.0, 'tsb' => 0.0];
        $acwr = $calc->acwr($daily, $today);

        // Intensity distribution over the last 28 days, from km splits.
        $cutoff = (new DateTimeImmutable($today))->modify('-27 days')->format('Y-m-d');
        $segments = [];
        foreach ($activities as $a) {
            if (substr((string) $a->occurred_at, 0, 10) < $cutoff) {
                continue;
            }
            $splits = $a->splits;
            if ($splits !== []) {
                foreach ($splits as $s) {
                    if (! is_array($s)) {
                        continue;
                    }
                    $dist = (float) ($s['distance_meters'] ?? 0);
                    $dur = (int) ($s['duration_seconds'] ?? 0);
                    if ($dist > 0.0 && $dur > 0) {
                        $segments[] = ['seconds' => $dur, 'paceSecondsPerKm' => $dur * 1000 / $dist];
                    }
                }
            } elseif ((int) $a->moving_seconds > 0) {
                $segments[] = ['seconds' => (int) $a->moving_seconds, 'paceSecondsPerKm' => (float) $a->average_pace_seconds_per_km];
            }
        }
        $zones = $calc->intensityDistribution($segments, $marathon, $threshold);

        // Load/ACWR need a real chronic base; flag when history is still thin.
        $historyDays = (new DateTimeImmutable($first))->diff(new DateTimeImmutable($today))->days;

        return [
            'hasData' => true,
            'reliable' => $historyDays >= 21,
            'form' => (int) round($last['tsb']),
            'fitness' => (int) round($last['ctl']),
            'fatigue' => (int) round($last['atl']),
            'acwr' => $acwr,
            'series' => array_map(
                static fn (array $p): array => ['date' => $p['date'], 'ctl' => $p['ctl'], 'tsb' => $p['tsb']],
                $series,
            ),
            'zones' => [
                'easy' => $zones['easy'],
                'moderate' => $zones['moderate'],
                'hard' => $zones['hard'],
                'total' => $zones['easy'] + $zones['moderate'] + $zones['hard'],
            ],
        ];
    }
}
