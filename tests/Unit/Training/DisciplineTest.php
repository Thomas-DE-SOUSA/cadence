<?php

declare(strict_types=1);

use Cadence\Training\Domain\Enum\Discipline;

describe('Discipline::infer', function (): void {
    it('reads an ultra-trail from the race or distance', function (): void {
        expect(Discipline::infer('UTMB', 170000))->toBe(Discipline::ULTRA_TRAIL);
        expect(Discipline::infer('Trail des Templiers 76 km', 76000))->toBe(Discipline::ULTRA_TRAIL);
        expect(Discipline::infer('objectif finir', 50000))->toBe(Discipline::ULTRA_TRAIL);
    });

    it('reads a trail from wording or D+', function (): void {
        expect(Discipline::infer('Trail du Ventoux 25 km 1500 D+', 25000))->toBe(Discipline::TRAIL);
    });

    it('splits road distances', function (): void {
        expect(Discipline::infer('Odysséa 10 km', 10000))->toBe(Discipline::ROUTE_10K);
        expect(Discipline::infer('Semi de Paris', 21097))->toBe(Discipline::ROUTE_SEMI);
        expect(Discipline::infer('Marathon de Paris', 42195))->toBe(Discipline::ROUTE_MARATHON);
    });

    it('does not read a semi-marathon as a marathon', function (): void {
        expect(Discipline::infer('Semi-marathon de Boulogne', 21097))->toBe(Discipline::ROUTE_SEMI);
    });

    it('defaults to short road when nothing matches', function (): void {
        expect(Discipline::infer('', null))->toBe(Discipline::ROUTE_10K);
    });
});

describe('Discipline coaching model', function (): void {
    it('treats road as pace-based and trail/ultra as effort-based', function (): void {
        expect(Discipline::ROUTE_10K->usesPace())->toBeTrue();
        expect(Discipline::ULTRA_TRAIL->usesPace())->toBeFalse();
        expect(Discipline::TRAIL->usesPace())->toBeFalse();
    });

    it('gives every discipline a non-empty playbook', function (): void {
        foreach (Discipline::cases() as $d) {
            expect(trim($d->playbook()))->not->toBe('');
        }
        expect(Discipline::ULTRA_TRAIL->playbook())->toContain('TEMPS D\'EFFORT');
    });
});
