<?php

declare(strict_types=1);

use Cadence\Coaching\Domain\Service\VdotCalculator;

describe('VdotCalculator', function (): void {
    $calc = new VdotCalculator();

    it('estimates VDOT from a race in line with the Daniels tables', function () use ($calc): void {
        // Reference points from Daniels' VDOT tables.
        expect($calc->vdot(10000, 2400))->toBeGreaterThan(51.5)->toBeLessThan(52.5); // 10 km in 40:00 ≈ 52
        expect($calc->vdot(5000, 1200))->toBeGreaterThan(49.5)->toBeLessThan(50.5);   // 5 km in 20:00 ≈ 50
        expect($calc->vdot(10010, 2553))->toBeGreaterThan(47.5)->toBeLessThan(49.0);  // ~42:30 ≈ VDOT 48
    });

    it('derives training paces that get faster from easy to repetition', function () use ($calc): void {
        $vdot = 48.0;
        $easy = $calc->paceSecondsPerKm($vdot, VdotCalculator::EASY);
        $marathon = $calc->paceSecondsPerKm($vdot, VdotCalculator::MARATHON);
        $threshold = $calc->paceSecondsPerKm($vdot, VdotCalculator::THRESHOLD);
        $interval = $calc->paceSecondsPerKm($vdot, VdotCalculator::INTERVAL);
        $repetition = $calc->paceSecondsPerKm($vdot, VdotCalculator::REPETITION);

        expect($easy)->toBeGreaterThan($marathon);
        expect($marathon)->toBeGreaterThan($threshold);
        expect($threshold)->toBeGreaterThan($interval);
        expect($interval)->toBeGreaterThan($repetition);

        // Sanity: threshold for VDOT 48 sits around 4:29/km.
        expect($threshold)->toBeGreaterThan(258)->toBeLessThan(280);
        expect($easy)->toBeGreaterThan(305)->toBeLessThan(330);
    });

    it('rejects non-positive inputs', function () use ($calc): void {
        expect(fn (): float => $calc->vdot(0, 100))->toThrow(InvalidArgumentException::class);
        expect(fn (): float => $calc->vdot(1000, 0))->toThrow(InvalidArgumentException::class);
    });
});
