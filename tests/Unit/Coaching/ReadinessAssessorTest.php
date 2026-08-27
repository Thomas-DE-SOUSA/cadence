<?php

declare(strict_types=1);

use Cadence\Coaching\Domain\Service\ReadinessAssessor;
use Cadence\Coaching\Domain\ValueObject\ReadinessLevel;
use Cadence\Coaching\Domain\ValueObject\WellnessCheckIn;

function checkin(int $s, int $e, int $l, int $m, int $pain = 0, string $where = ''): WellnessCheckIn
{
    return new WellnessCheckIn('2026-08-27', $s, $e, $l, $m, $pain, $where);
}

describe('ReadinessAssessor', function (): void {
    $assessor = new ReadinessAssessor();

    it('reads great sensations without pain as green', function () use ($assessor): void {
        $r = $assessor->assess(checkin(5, 5, 5, 5));
        expect($r->level)->toBe(ReadinessLevel::GREEN);
        expect($r->score)->toBe(100);
    });

    it('reads flat sensations as red', function () use ($assessor): void {
        $r = $assessor->assess(checkin(2, 2, 1, 2));
        expect($r->level)->toBe(ReadinessLevel::RED);
    });

    it('lands mid sensations in amber', function () use ($assessor): void {
        $r = $assessor->assess(checkin(4, 3, 3, 4));
        expect($r->level)->toBe(ReadinessLevel::AMBER);
    });

    it('lets a limiting pain override otherwise great sensations (hard red)', function () use ($assessor): void {
        // Everything feels perfect but a pain limits running → must not read green.
        $r = $assessor->assess(checkin(5, 5, 5, 5, 3, 'genou'));
        expect($r->level)->toBe(ReadinessLevel::RED);
        expect($r->score)->toBeLessThanOrEqual(25);
        expect($r->advice)->toContain('repos');
    });

    it('caps a moderate pain at amber even with good sensations', function () use ($assessor): void {
        $r = $assessor->assess(checkin(5, 5, 5, 5, 2, 'mollet'));
        expect($r->level)->toBe(ReadinessLevel::AMBER);
        expect($r->advice)->toContain('mollet');
    });
});

describe('WellnessCheckIn', function (): void {
    it('rejects an out-of-range sensation', function (): void {
        expect(fn () => checkin(6, 3, 3, 3))->toThrow(InvalidArgumentException::class);
    });

    it('rejects an out-of-range pain level', function (): void {
        expect(fn () => new WellnessCheckIn('2026-08-27', 3, 3, 3, 3, 4))->toThrow(InvalidArgumentException::class);
    });

    it('flags a limiting pain', function (): void {
        expect(checkin(3, 3, 3, 3, 3)->limitsRunning())->toBeTrue();
        expect(checkin(3, 3, 3, 3, 1)->limitsRunning())->toBeFalse();
    });
});
