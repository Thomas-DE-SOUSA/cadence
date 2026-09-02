<?php

declare(strict_types=1);

use Cadence\Coaching\Infrastructure\Read\StrengthWeeklyBrief;
use Cadence\Strength\Domain\Enum\MuscleGroup;
use Cadence\Strength\Domain\Enum\WeighMoment;
use Cadence\Strength\Domain\Enum\WorkoutStatus;
use Cadence\Strength\Domain\Model\StrengthSession;
use Cadence\Strength\Domain\ValueObject\PerformedExercise;
use Cadence\Strength\Domain\ValueObject\SetEntry;
use Cadence\Strength\Domain\ValueObject\WeightEntry;

/** @param list<SetEntry> $sets */
function performed(string $exerciseId, string $name, array $sets): PerformedExercise
{
    return new PerformedExercise($exerciseId, $name, $sets);
}

function workingSet(float $weightKg, int $reps): SetEntry
{
    return new SetEntry($weightKg, $reps, null, null, false, true);
}

/** @param list<PerformedExercise> $exercises */
function doneSession(string $date, array $exercises): StrengthSession
{
    return new StrengthSession('s-'.$date, 'tenant-thomas', $date, 'W', '', null, $exercises, WorkoutStatus::DONE);
}

describe('Feature: strength weekly brief', function (): void {
    it('aggregates the current Mon–Sun week and contrasts the previous one', function (): void {
        $muscleOf = ['bench' => MuscleGroup::CHEST, 'squat' => MuscleGroup::QUADS];

        $sessions = [
            // This week (Mon 2026-08-31 .. Sun 2026-09-06): 3 chest + 2 leg working sets.
            doneSession('2026-09-01', [
                performed('bench', 'Bench', [workingSet(60, 10), workingSet(60, 10), workingSet(60, 10)]),
                performed('squat', 'Squat', [workingSet(100, 5), workingSet(100, 5)]),
            ]),
            // Previous week (2026-08-24 .. 2026-08-30).
            doneSession('2026-08-25', [
                performed('bench', 'Bench', [workingSet(60, 10), workingSet(60, 10), workingSet(60, 10)]),
            ]),
            // Planned session in this week — must be ignored (not DONE).
            new StrengthSession('planned', 'tenant-thomas', '2026-09-02', 'W', '', null, [
                performed('bench', 'Bench', [workingSet(60, 10)]),
            ], WorkoutStatus::PLANNED),
        ];

        $weight = [
            new WeightEntry('2026-09-01', WeighMoment::MORNING, 72.0),
            new WeightEntry('2026-09-02', WeighMoment::MORNING, 73.0),
            new WeightEntry('2026-08-25', WeighMoment::MORNING, 74.0),
        ];

        $summary = StrengthWeeklyBrief::summarise($sessions, $muscleOf, $weight, '2026-09-02');

        expect($summary->weekStart)->toBe('2026-08-31');
        expect($summary->weekEnd)->toBe('2026-09-06');
        expect($summary->sessions)->toBe(1);
        expect($summary->workingSets)->toBe(5);
        expect($summary->tonnageKg)->toBe(2800.0);
        expect($summary->legSessions)->toBe(1);
        expect($summary->prevSessions)->toBe(1);
        expect($summary->prevTonnageKg)->toBe(1800.0);
        expect($summary->daysSinceLast)->toBe(1);
        expect($summary->muscleBalance)->toBe([
            ['muscle' => 'CHEST', 'label' => 'Pectoraux', 'sets' => 3],
            ['muscle' => 'QUADS', 'label' => 'Quadriceps', 'sets' => 2],
        ]);
        expect($summary->weightAvgKg)->toBe(72.5);
        expect($summary->weightPrevAvgKg)->toBe(74.0);
    });

    it('reports no data when there are no done sessions', function (): void {
        $summary = StrengthWeeklyBrief::summarise([], [], [], '2026-09-02');

        expect($summary->hasData())->toBeFalse();
        expect($summary->sessions)->toBe(0);
        expect($summary->muscleBalance)->toBe([]);
        expect($summary->weightAvgKg)->toBeNull();
    });
});
