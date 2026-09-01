<?php

declare(strict_types=1);

use Cadence\Strength\Domain\Enum\WeighMoment;
use Cadence\Strength\Domain\ValueObject\WeightEntry;
use Cadence\Strength\Infrastructure\Read\WeightView;

describe('Feature: weekly weight averages', function (): void {
    it('averages morning and evening readings within one Mon–Sun week', function (): void {
        $entries = [
            new WeightEntry('2026-08-31', WeighMoment::MORNING, 72.0), // Monday
            new WeightEntry('2026-09-06', WeighMoment::EVENING, 74.0), // Sunday, same ISO week
        ];

        $weeks = WeightView::weeklyAverages($entries);

        expect($weeks)->toHaveCount(1);
        expect($weeks[0]['weekStart'])->toBe('2026-08-31');
        expect($weeks[0]['avgKg'])->toBe(73.0);
        expect($weeks[0]['count'])->toBe(2);
    });

    it('splits readings that fall in different weeks, oldest first', function (): void {
        $entries = [
            new WeightEntry('2026-08-31', WeighMoment::MORNING, 74.0), // Monday → week of Aug 31
            new WeightEntry('2026-08-30', WeighMoment::MORNING, 72.0), // Sunday → week of Aug 24
        ];

        $weeks = WeightView::weeklyAverages($entries);

        expect($weeks)->toHaveCount(2);
        expect($weeks[0]['weekStart'])->toBe('2026-08-24');
        expect($weeks[1]['weekStart'])->toBe('2026-08-31');
    });

    it('rejects a non-positive weight at the value-object boundary', function (): void {
        expect(fn (): WeightEntry => new WeightEntry('2026-08-31', WeighMoment::MORNING, 0.0))
            ->toThrow(InvalidArgumentException::class);
    });
});
