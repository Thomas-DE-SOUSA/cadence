<?php

declare(strict_types=1);

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Activity\Infrastructure\Read\ProgressionView;

function progressionRun(string $id, string $date, int $distance, int $seconds, array $splits): ActivityModel
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
function evenSplits(int $km, int $perKm): array
{
    $splits = [];
    for ($i = 1; $i <= $km; $i++) {
        $splits[] = ['index' => $i, 'distance_meters' => 1000, 'duration_seconds' => $perKm];
    }

    return $splits;
}

describe('ProgressionView', function (): void {
    $goal = ['label' => '10 km sous 40:00', 'raceName' => 'Odysséa', 'raceDate' => '2026-10-04', 'distanceMeters' => 10000, 'targetSeconds' => 2400];

    it('tracks the goal with a gap and a race countdown', function () use ($goal): void {
        $run = progressionRun('A', '2026-08-19T18:00:00+00:00', 10000, 2545, evenSplits(10, 254));

        $view = ProgressionView::build([$run], new DateTimeImmutable('2026-08-20'), $goal);

        expect($view['goal']['currentSeconds'])->toBe(2540); // 10 × 254
        expect($view['goal']['achieved'])->toBeFalse();
        expect($view['goal']['gapSeconds'])->toBe(140);
        expect($view['goal']['daysLeft'])->toBe(45); // 2026-08-20 → 2026-10-04
        expect($view['goal']['weeksLeft'])->toBe(7);
        expect($view['goal']['progressPct'])->toBeGreaterThan(90)->toBeLessThan(100);
    });

    it('marks the goal achieved when a run beats the target', function () use ($goal): void {
        $run = progressionRun('A', '2026-08-19T18:00:00+00:00', 10000, 2380, evenSplits(10, 238));

        $view = ProgressionView::build([$run], new DateTimeImmutable('2026-08-20'), $goal);

        expect($view['goal']['achieved'])->toBeTrue();
        expect($view['goal']['progressPct'])->toBe(100);
        expect($view['goal']['gapSeconds'])->toBe(0);
    });

    it('builds records, a VDOT estimate and a Riegel projection', function () use ($goal): void {
        $ten = progressionRun('A', '2026-08-10T18:00:00+00:00', 10000, 2600, evenSplits(10, 260));
        $five = progressionRun('B', '2026-08-19T18:00:00+00:00', 5000, 1180, evenSplits(5, 236));

        $view = ProgressionView::build([$five, $ten], new DateTimeImmutable('2026-08-20'), $goal);

        // Records: one per distance, sorted ascending by distance.
        expect(array_column($view['records'], 'distanceMeters'))->toBe([1000, 3000, 5000, 10000]);
        // VDOT is a sane running value.
        expect($view['vdot'])->toBeGreaterThan(40)->toBeLessThan(70);
        // Riegel projects the 10 km from the best shorter effort (5 km).
        expect($view['projection']['fromDistanceMeters'])->toBe(5000);
        expect($view['projection']['toDistanceMeters'])->toBe(10000);
        expect($view['projection']['predictedSeconds'])->toBeGreaterThan(1180 * 2);
    });

    it('returns no goal when the program has none', function (): void {
        $run = progressionRun('A', '2026-08-19T18:00:00+00:00', 5000, 1400, evenSplits(5, 280));

        $view = ProgressionView::build([$run], new DateTimeImmutable('2026-08-20'), null);

        expect($view['goal'])->toBeNull();
        expect($view['projection'])->toBeNull();
        expect($view['records'])->not->toBeEmpty();
    });
});
