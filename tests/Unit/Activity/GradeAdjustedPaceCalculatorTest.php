<?php

declare(strict_types=1);

use Cadence\Activity\Domain\Service\GradeAdjustedPaceCalculator;

beforeEach(function (): void {
    $this->gap = new GradeAdjustedPaceCalculator();
});

describe('GradeAdjustedPaceCalculator', function (): void {
    it('is neutral on the flat', function (): void {
        expect($this->gap->costRatio(0.0))->toBe(1.0);
        expect($this->gap->adjustedPace(300.0, 0.0))->toBe(300.0);
        expect($this->gap->overall([['distanceMeters' => 1000, 'durationSeconds' => 300, 'elevationMeters' => 0]]))->toBe(300);
    });

    it('costs more uphill (GAP faster than the actual pace)', function (): void {
        expect($this->gap->costRatio(0.08))->toBeGreaterThan(1.0);
        // Slogging 6:00/km up an 8% grade is worth a faster flat pace.
        expect($this->gap->adjustedPace(360.0, 0.08))->toBeLessThan(360.0);
    });

    it('costs less on a gentle descent (GAP slower than the actual pace)', function (): void {
        expect($this->gap->costRatio(-0.08))->toBeLessThan(1.0);
        expect($this->gap->adjustedPace(300.0, -0.08))->toBeGreaterThan(300.0);
    });

    it('aggregates a hilly run to a flat-equivalent pace', function (): void {
        $flat = $this->gap->overall([['distanceMeters' => 1000, 'durationSeconds' => 330, 'elevationMeters' => 0]]);
        $climb = $this->gap->overall([['distanceMeters' => 1000, 'durationSeconds' => 330, 'elevationMeters' => 60]]);
        expect($climb)->toBeLessThan($flat); // same pace but uphill -> better GAP
    });

    it('returns null with no usable segments', function (): void {
        expect($this->gap->overall([]))->toBeNull();
        expect($this->gap->overall([['distanceMeters' => 0, 'durationSeconds' => 0, 'elevationMeters' => 0]]))->toBeNull();
    });
});
