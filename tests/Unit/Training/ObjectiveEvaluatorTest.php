<?php

declare(strict_types=1);

use Cadence\Training\Domain\Enum\ObjectiveType;
use Cadence\Training\Domain\Model\Objective;
use Cadence\Training\Domain\Service\ObjectiveEvaluator;
use Cadence\Training\Domain\ValueObject\ActivitySummary;

function objective(ObjectiveType $type, ?int $distance = null, ?int $seconds = null, ?float $pace = null, ?int $count = null): Objective
{
    return new Objective('obj-1', $type, 'test', $distance, $seconds, $pace, $count);
}

/** @param list<array{distanceMeters:int,durationSeconds:int}> $bestEfforts */
function run(int $distanceMeters, int $movingSeconds, float $pace, array $bestEfforts = []): ActivitySummary
{
    return new ActivitySummary('act-1', '2026-08-19T18:00:00+00:00', $distanceMeters, $movingSeconds, $pace, $bestEfforts);
}

describe('Feature: Objective evaluation (the coach)', function (): void {
    it('marks a race-time objective achieved when a run beats the target', function (): void {
        $o = objective(ObjectiveType::RACE_TIME, distance: 10000, seconds: 2400);

        expect(ObjectiveEvaluator::evaluate($o, [run(10050, 2350, 234.0)])->achieved)->toBeTrue()
            ->and(ObjectiveEvaluator::evaluate($o, [run(10050, 2555, 255.0)])->achieved)->toBeFalse();
    });

    it('uses best efforts for race-time objectives', function (): void {
        $o = objective(ObjectiveType::RACE_TIME, distance: 5000, seconds: 1200);
        $activity = run(10010, 2555, 255.0, [['distanceMeters' => 5000, 'durationSeconds' => 1160]]);

        expect(ObjectiveEvaluator::evaluate($o, [$activity])->achieved)->toBeTrue();
    });

    it('evaluates pace, longest run, volume and session count', function (): void {
        expect(ObjectiveEvaluator::evaluate(objective(ObjectiveType::PACE_OVER_DISTANCE, distance: 5000, pace: 240.0), [run(5000, 1175, 235.0)])->achieved)->toBeTrue()
            ->and(ObjectiveEvaluator::evaluate(objective(ObjectiveType::LONGEST_RUN, distance: 12000), [run(12500, 3600, 288.0)])->achieved)->toBeTrue()
            ->and(ObjectiveEvaluator::evaluate(objective(ObjectiveType::LONGEST_RUN, distance: 12000), [run(10000, 3000, 300.0)])->achieved)->toBeFalse()
            ->and(ObjectiveEvaluator::evaluate(objective(ObjectiveType::TOTAL_VOLUME, distance: 20000), [run(10010, 2555, 255.0), run(10010, 2555, 255.0)])->achieved)->toBeTrue()
            ->and(ObjectiveEvaluator::evaluate(objective(ObjectiveType::SESSION_COUNT, count: 2), [run(5000, 1500, 300.0), run(6000, 1800, 300.0)])->achieved)->toBeTrue();
    });

    it('reports partial progress toward a volume objective', function (): void {
        $result = ObjectiveEvaluator::evaluate(objective(ObjectiveType::TOTAL_VOLUME, distance: 20000), [run(10000, 2555, 255.0)]);

        expect($result->achieved)->toBeFalse()
            ->and($result->progress)->toEqualWithDelta(0.5, 0.01);
    });
});
