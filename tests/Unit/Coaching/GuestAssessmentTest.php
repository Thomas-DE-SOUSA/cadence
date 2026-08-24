<?php

declare(strict_types=1);

use Cadence\Coaching\Infrastructure\Read\GuestAssessment;

/** @return list<array{distanceMeters:int,durationSeconds:int}> */
function splits(array $perKmSeconds): array
{
    return array_map(static fn (int $s): array => ['distanceMeters' => 1000, 'durationSeconds' => $s], $perKmSeconds);
}

describe('GuestAssessment', function (): void {
    it('extracts best efforts per distance and a sane VDOT', function (): void {
        // 5 km at 4:00/km (240 s) → VDOT ≈ 50.
        $best = GuestAssessment::bestEffortsFromSplits(splits([240, 240, 240, 240, 240]));

        expect($best)->toHaveKeys([1000, 3000, 5000]);
        expect($best[5000])->toBe(1200);

        $assessment = GuestAssessment::assess($best);
        expect($assessment['vdot'])->toBeGreaterThan(45.0)->toBeLessThan(55.0);
        // Projections cover 5–21 km.
        expect(array_column($assessment['projections'], 'distanceMeters'))->toBe([5000, 10000, 15000, 20000, 21097]);
    });

    it('ignores a stopped / GPS-drift split instead of extrapolating', function (): void {
        // 9 clean km + a final "km" that is really a 20 min stop.
        $best = GuestAssessment::bestEffortsFromSplits(splits([250, 250, 250, 250, 250, 250, 250, 250, 250, 1200]));

        // A clean 5 km effort exists…
        expect($best)->toHaveKey(5000);
        expect($best[5000])->toBe(1250);
        // …but no 10 km, because the only 10-window includes the stopped split.
        expect($best)->not->toHaveKey(10000);
    });

    it('returns nothing without efforts', function (): void {
        $assessment = GuestAssessment::assess([]);
        expect($assessment['vdot'])->toBeNull();
        expect($assessment['efforts'])->toBe([]);
        expect($assessment['projections'])->toBe([]);
    });
});
