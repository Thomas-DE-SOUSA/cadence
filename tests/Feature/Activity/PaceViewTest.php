<?php

declare(strict_types=1);

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Activity\Infrastructure\Read\PaceView;

function paceRun(string $id, string $date, int $distance, int $seconds, array $splits): ActivityModel
{
    $m = new ActivityModel();
    $m->id = $id;
    $m->occurred_at = $date;
    $m->source = 'GPX';
    $m->distance_meters = $distance;
    $m->moving_seconds = $seconds;
    $m->average_pace_seconds_per_km = $seconds / ($distance / 1000);
    $m->elevation_gain_meters = 0;
    $m->best_efforts = [];
    $m->splits = $splits;

    return $m;
}

/** @return list<array{index:int,distance_meters:int,duration_seconds:int}> */
function flatSplits(int $km, int $perKm): array
{
    $splits = [];
    for ($i = 1; $i <= $km; $i++) {
        $splits[] = ['index' => $i, 'distance_meters' => 1000, 'duration_seconds' => $perKm];
    }

    return $splits;
}

describe('PaceView', function (): void {
    it('derives VDOT and ordered pace zones from the best 10 km', function (): void {
        $run = paceRun('A', '2026-08-19T18:00:00+00:00', 10000, 2400, flatSplits(10, 240));

        $view = PaceView::build([$run]);

        expect($view['vdot'])->toBeGreaterThan(51)->toBeLessThan(53); // 10 km in 40:00 ≈ 52
        expect($view['basis']['distanceMeters'])->toBe(10000);
        expect($view['basis']['label'])->toBe('10 km');

        // Six zones, from recovery (slowest) to repetition (fastest).
        $zones = collect($view['zones'])->keyBy('key');
        expect($zones->keys()->all())->toBe(['recovery', 'easy', 'marathon', 'threshold', 'interval', 'repetition']);
        expect($zones['recovery']['minSeconds'])->toBeGreaterThan($zones['repetition']['maxSeconds']);
        // Easy is a range; marathon is a single pace.
        expect($zones['easy']['minSeconds'])->toBeLessThan($zones['easy']['maxSeconds']);
        expect($zones['marathon']['minSeconds'])->toBe($zones['marathon']['maxSeconds']);
    });

    it('projects equivalent race paces and flags the basis distance', function (): void {
        $run = paceRun('A', '2026-08-19T18:00:00+00:00', 10000, 2400, flatSplits(10, 240));

        $view = PaceView::build([$run]);
        $byDistance = collect($view['racePaces'])->keyBy('distanceMeters');

        expect($byDistance[10000]['isBasis'])->toBeTrue();
        expect($byDistance[10000]['seconds'])->toBe(2400);
        // A slower/faster pair around the basis: 5 km faster, marathon slower.
        expect($byDistance[5000]['paceSeconds'])->toBeLessThan($byDistance[10000]['paceSeconds']);
        expect($byDistance[42195]['paceSeconds'])->toBeGreaterThan($byDistance[10000]['paceSeconds']);
    });

    it('returns empty zones without any effort', function (): void {
        $view = PaceView::build([]);

        expect($view['vdot'])->toBeNull();
        expect($view['basis'])->toBeNull();
        expect($view['zones'])->toBe([]);
    });
});
