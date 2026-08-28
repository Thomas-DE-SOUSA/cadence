<?php

declare(strict_types=1);

use Cadence\Strength\Domain\Model\StrengthSession;
use Cadence\Strength\Domain\Service\OneRepMaxCalculator;
use Cadence\Strength\Domain\ValueObject\PerformedExercise;
use Cadence\Strength\Domain\ValueObject\SetEntry;

describe('OneRepMaxCalculator (Epley)', function (): void {
    $calc = new OneRepMaxCalculator();

    it('returns the weight itself for a single rep', function () use ($calc): void {
        expect($calc->epley(100, 1))->toBe(100.0);
    });

    it('estimates a higher 1RM as reps rise at the same weight', function () use ($calc): void {
        // 100 kg × 5 reps → 100 × (1 + 5/30) ≈ 116.7
        expect(round($calc->epley(100, 5), 1))->toBe(116.7);
        expect($calc->epley(100, 5))->toBeGreaterThan($calc->epley(100, 3));
    });

    it('is zero for non-positive input', function () use ($calc): void {
        expect($calc->epley(0, 5))->toBe(0.0);
        expect($calc->epley(80, 0))->toBe(0.0);
    });

    it('takes the best working set of an exercise (ignoring warm-ups)', function () use ($calc): void {
        $ex = new PerformedExercise('sq', 'Squat', [
            new SetEntry(60, 5, null, null, isWarmup: true),   // warm-up, ignored
            new SetEntry(100, 5),                              // e1RM ≈ 116.7
            new SetEntry(110, 2),                              // e1RM ≈ 117.3 → best
        ]);

        expect(round($calc->bestForExercise($ex), 1))->toBe(117.3);
    });
});

describe('SetEntry', function (): void {
    it('counts working volume but not warm-up or bodyweight/timed sets', function (): void {
        expect((new SetEntry(80, 10))->volumeKg())->toBe(800.0);
        expect((new SetEntry(80, 10, isWarmup: true))->isWorking())->toBeFalse();
        expect((new SetEntry(null, 12))->volumeKg())->toBe(0.0);     // bodyweight
        expect((new SetEntry(null, null, null, 60))->volumeKg())->toBe(0.0); // 60s plank
    });

    it('rejects an RPE outside 0..10', function (): void {
        expect(fn () => new SetEntry(80, 5, 11))->toThrow(InvalidArgumentException::class);
    });
});

describe('StrengthSession', function (): void {
    it('sums working sets and volume, and round-trips through a snapshot', function (): void {
        $session = new StrengthSession('s1', 'tenant-thomas', '2026-08-28', 'Push', '', 3600, [
            new PerformedExercise('bp', 'Développé couché', [
                new SetEntry(60, 10, null, null, isWarmup: true),
                new SetEntry(80, 8, 8.0),
                new SetEntry(80, 8, 9.0),
            ]),
            new PerformedExercise('plank', 'Gainage', [
                new SetEntry(null, null, null, 60),
            ], perSide: false),
        ]);

        expect($session->totalSets())->toBe(3);           // 2 working bench + 1 plank
        expect($session->totalVolumeKg())->toBe(1280.0);  // 80*8 + 80*8

        $restored = StrengthSession::fromSnapshot($session->toSnapshot());
        expect($restored->totalVolumeKg())->toBe(1280.0);
        expect($restored->id())->toBe('s1');
    });
});
