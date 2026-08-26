<?php

declare(strict_types=1);

use Cadence\Coaching\Domain\Service\TrainingLoadCalculator;

beforeEach(function (): void {
    $this->calc = new TrainingLoadCalculator();
});

describe('TrainingLoadCalculator::stress', function (): void {
    it('scores an hour at threshold at 100', function (): void {
        expect($this->calc->stress(3600, 266.0, 266))->toBe(100.0);
    });

    it('scales with intensity squared', function (): void {
        // IF = 266/532 = 0.5 -> 0.25 * 100
        expect($this->calc->stress(3600, 532.0, 266))->toBe(25.0);
    });

    it('caps the intensity factor for very fast segments', function (): void {
        // IF would be 1.33 but is capped at 1.15 -> 1.3225 * 100
        expect(round($this->calc->stress(3600, 200.0, 266), 2))->toBe(132.25);
    });

    it('returns zero for degenerate input', function (): void {
        expect($this->calc->stress(0, 266.0, 266))->toBe(0.0);
        expect($this->calc->stress(3600, 0.0, 266))->toBe(0.0);
    });
});

describe('TrainingLoadCalculator::formSeries', function (): void {
    it('builds one point per day and leaves fatigue above fitness after a hard day', function (): void {
        $series = $this->calc->formSeries(['2026-08-01' => 100.0], '2026-08-01', '2026-08-03');

        expect($series)->toHaveCount(3);
        // Day 1: form measured before the session -> 0; fitness rises, fatigue rises faster.
        expect($series[0]['tsb'])->toBe(0.0);
        expect($series[0]['ctl'])->toBeGreaterThan(0.0);
        expect($series[0]['atl'])->toBeGreaterThan($series[0]['ctl']);
        // Day 2: form is now negative (fatigue > fitness).
        expect($series[1]['tsb'])->toBeLessThan(0.0);
    });
});

describe('TrainingLoadCalculator::acwr', function (): void {
    it('is ~1.0 under steady load and spikes when the week jumps', function (): void {
        $steady = [];
        for ($i = 0; $i < 28; $i++) {
            $steady[(new DateTimeImmutable('2026-08-28'))->modify("-{$i} days")->format('Y-m-d')] = 100.0;
        }
        expect($this->calc->acwr($steady, '2026-08-28'))->toBe(1.0);

        // Load only in the last 7 days -> big acute:chronic spike.
        $spike = [];
        for ($i = 0; $i < 7; $i++) {
            $spike[(new DateTimeImmutable('2026-08-28'))->modify("-{$i} days")->format('Y-m-d')] = 100.0;
        }
        expect($this->calc->acwr($spike, '2026-08-28'))->toBe(4.0);
    });
});

describe('TrainingLoadCalculator::intensityDistribution', function (): void {
    it('classifies segments by pace against the athlete zones', function (): void {
        $segments = [
            ['seconds' => 1800, 'paceSecondsPerKm' => 315.0], // easy (>= marathon 277)
            ['seconds' => 600, 'paceSecondsPerKm' => 270.0],  // moderate (threshold 266 <= p < 277)
            ['seconds' => 300, 'paceSecondsPerKm' => 240.0],  // hard (< threshold)
        ];

        expect($this->calc->intensityDistribution($segments, 277, 266))
            ->toBe(['easy' => 1800, 'moderate' => 600, 'hard' => 300]);
    });
});
