<?php

declare(strict_types=1);

use Cadence\Coaching\Domain\Service\FitnessAssessor;
use Cadence\Coaching\Domain\Service\VdotCalculator;
use Cadence\Coaching\Domain\ValueObject\PerformancePoint;

describe('FitnessAssessor', function (): void {
    $assessor = new FitnessAssessor(new VdotCalculator());

    it('builds a snapshot from the best effort and recent load', function () use ($assessor): void {
        $snapshot = $assessor->assess([
            new PerformancePoint(5070, 1430, '2026-08-20T00:00:00+00:00'), // recovery, slow
            new PerformancePoint(10010, 2553, '2026-08-19T18:00:00+00:00'), // 10 km test, fast
        ]);

        expect($snapshot)->not->toBeNull();
        // VDOT comes from the faster 10 km, not the recovery run.
        expect($snapshot->vdot)->toBeGreaterThan(47.5)->toBeLessThan(49.0);
        expect($snapshot->referenceDistanceMeters)->toBe(10010);
        expect($snapshot->referenceDate)->toBe('2026-08-19');
        expect($snapshot->recentVolumeMeters)->toBe(15080);
        expect($snapshot->longestRunMeters)->toBe(10010);
        expect($snapshot->recentRunCount)->toBe(2);
        expect($snapshot->paces->easy)->toBeGreaterThan($snapshot->paces->threshold);
    });

    it('returns null without a qualifying effort', function () use ($assessor): void {
        expect($assessor->assess([]))->toBeNull();
        expect($assessor->assess([new PerformancePoint(800, 200, '2026-08-19T18:00:00+00:00')]))->toBeNull();
    });
});
