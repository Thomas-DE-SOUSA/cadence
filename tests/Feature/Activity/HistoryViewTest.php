<?php

declare(strict_types=1);

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Activity\Infrastructure\Read\HistoryView;

function activity(string $id, string $date, int $distance, int $seconds, float $pace, array $bestEfforts): ActivityModel
{
    $m = new ActivityModel();
    $m->id = $id;
    $m->occurred_at = $date;
    $m->source = 'STRAVA';
    $m->distance_meters = $distance;
    $m->moving_seconds = $seconds;
    $m->average_pace_seconds_per_km = $pace;
    $m->elevation_gain_meters = 0;
    $m->best_efforts = $bestEfforts;

    return $m;
}

describe('HistoryView', function (): void {
    it('ranks efforts across runs into records and medals', function (): void {
        $fast = activity('A', '2026-08-19T18:00:00+00:00', 10010, 2553, 255.0, [
            ['distance_meters' => 5000, 'duration_seconds' => 1160],
            ['distance_meters' => 10000, 'duration_seconds' => 2621],
        ]);
        $slow = activity('B', '2026-08-20T00:00:00+00:00', 5070, 1409, 282.0, [
            ['distance_meters' => 5000, 'duration_seconds' => 1409],
            ['distance_meters' => 1000, 'duration_seconds' => 220],
        ]);

        $view = HistoryView::build([$fast, $slow], new DateTimeImmutable('2026-08-21'));

        // Records: one per distance, best time, sorted by distance.
        expect(array_column($view['records'], 'label'))->toBe(['1 km', '5 km', '10 km']);
        $fiveKm = collect($view['records'])->firstWhere('distanceMeters', 5000);
        expect($fiveKm['durationSeconds'])->toBe(1160);
        expect($fiveKm['activityId'])->toBe('A');

        // Medals: A holds both PRs (gold); B is 2nd on 5 km and gold on 1 km.
        $a = collect($view['activities'])->firstWhere('id', 'A');
        expect(collect($a['medals'])->pluck('rank')->all())->toBe([1, 1]);
        $b = collect($view['activities'])->firstWhere('id', 'B');
        $bFive = collect($b['medals'])->firstWhere('distanceMeters', 5000);
        expect($bFive['rank'])->toBe(2);
    });

    it('derives records from km splits, never slower than the full run', function (): void {
        $splits = [];
        foreach ([226, 194, 211, 273, 274, 269, 316, 299, 222, 264] as $i => $sec) {
            $splits[] = ['index' => $i + 1, 'distance_meters' => 1001, 'duration_seconds' => $sec];
        }
        $run = activity('A', '2026-08-19T18:00:00+00:00', 10010, 2555, 255.0, []);
        $run->splits = $splits;

        $view = HistoryView::build([$run], new DateTimeImmutable('2026-08-21'));
        $records = collect($view['records'])->keyBy('label');

        // Best 10 km must not exceed the full run (2555 s).
        expect($records['10 km']['durationSeconds'])->toBeLessThanOrEqual(2555)->toBeGreaterThan(2500);
        // Best single km is the fastest split (194 s), scaled to 1000 m.
        expect($records['1 km']['durationSeconds'])->toBeLessThan(200);
        // Efforts get faster as the distance shrinks.
        expect($records['1 km']['paceSecondsPerKm'])->toBeLessThan($records['10 km']['paceSecondsPerKm']);
    });

    it('computes the weekly streak and stats', function (): void {
        $view = HistoryView::build(
            [
                activity('A', '2026-08-20T00:00:00+00:00', 5000, 1400, 280.0, []),
                activity('B', '2026-08-13T00:00:00+00:00', 8000, 2400, 300.0, []),
            ],
            new DateTimeImmutable('2026-08-21'), // same ISO week as Aug 20; Aug 13 is the prior week
        );

        expect($view['streak']['weeks'])->toBe(2);
        expect($view['streak']['days'])->toHaveCount(7);
        expect($view['stats']['totalActivities'])->toBe(2);
        expect($view['stats']['totalDistanceMeters'])->toBe(13000);
        expect($view['stats']['thisWeekMeters'])->toBe(5000);
    });

    it('unlocks achievements from the data', function (): void {
        $view = HistoryView::build(
            [activity('A', '2026-08-19T18:00:00+00:00', 10010, 2553, 255.0, [['distance_meters' => 10000, 'duration_seconds' => 2380]])],
            new DateTimeImmutable('2026-08-21'),
        );

        $byId = collect($view['achievements'])->keyBy('id');
        expect($byId['ten']['unlocked'])->toBeTrue();
        expect($byId['sub40']['unlocked'])->toBeTrue(); // 10 km in 39:40
        expect($byId['vol100']['unlocked'])->toBeFalse();
    });
});
