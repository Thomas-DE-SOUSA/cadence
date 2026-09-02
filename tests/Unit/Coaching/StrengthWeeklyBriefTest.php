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
function performed(string $exerciseId, array $sets): PerformedExercise
{
    return new PerformedExercise($exerciseId, ucfirst($exerciseId), $sets);
}

function workingSet(float $weightKg, int $reps): SetEntry
{
    return new SetEntry($weightKg, $reps, null, null, false, true);
}

function warmupSet(float $weightKg, int $reps): SetEntry
{
    return new SetEntry($weightKg, $reps, null, null, true, true);
}

/** @param list<PerformedExercise> $exercises */
function muscuSession(string $date, array $exercises, WorkoutStatus $status = WorkoutStatus::DONE): StrengthSession
{
    return new StrengthSession('s-'.$date.'-'.$status->value, 'tenant-thomas', $date, 'W', '', null, $exercises, $status);
}

describe('Feature: strength weekly brief', function (): void {
    it('aggregates the current Mon–Sun week and contrasts the previous one', function (): void {
        $muscleOf = ['bench' => MuscleGroup::CHEST, 'squat' => MuscleGroup::QUADS];

        $sessions = [
            muscuSession('2026-09-01', [
                performed('bench', [workingSet(60, 10), workingSet(60, 10), workingSet(60, 10)]),
                performed('squat', [workingSet(100, 5), workingSet(100, 5)]),
            ]),
            muscuSession('2026-08-25', [performed('bench', [workingSet(60, 10), workingSet(60, 10), workingSet(60, 10)])]),
            muscuSession('2026-09-02', [performed('bench', [workingSet(60, 10)])], WorkoutStatus::PLANNED),
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

    it('breaks muscle-set ties by muscle key, not by session order', function (): void {
        $muscleOf = ['bench' => MuscleGroup::CHEST, 'row' => MuscleGroup::BACK];
        // BACK and CHEST both at 2 sets → BACK first (B < C), regardless of order seen.
        $sessions = [muscuSession('2026-09-01', [
            performed('bench', [workingSet(60, 10), workingSet(60, 10)]),
            performed('row', [workingSet(50, 10), workingSet(50, 10)]),
        ])];

        $summary = StrengthWeeklyBrief::summarise($sessions, $muscleOf, [], '2026-09-02');

        expect(array_column($summary->muscleBalance, 'muscle'))->toBe(['BACK', 'CHEST']);
    });

    it('includes sessions dated exactly on Monday and on Sunday', function (): void {
        $muscleOf = ['bench' => MuscleGroup::CHEST];
        $sessions = [
            muscuSession('2026-08-31', [performed('bench', [workingSet(60, 10)])]), // Monday (weekStart)
            muscuSession('2026-09-06', [performed('bench', [workingSet(60, 10)])]), // Sunday (weekEnd)
        ];

        // today = Sunday, so the Sunday session is not in the future.
        $summary = StrengthWeeklyBrief::summarise($sessions, $muscleOf, [], '2026-09-06');

        expect($summary->sessions)->toBe(2);
        expect($summary->daysSinceLast)->toBe(0);
    });

    it('ignores future-dated done sessions everywhere (count, tonnage, days-since)', function (): void {
        $muscleOf = ['bench' => MuscleGroup::CHEST];
        $sessions = [
            muscuSession('2026-09-01', [performed('bench', [workingSet(60, 10), workingSet(60, 10), workingSet(60, 10)])]),
            muscuSession('2026-09-05', [performed('bench', [workingSet(80, 10), workingSet(80, 10)])]), // future, still in week window
        ];

        $summary = StrengthWeeklyBrief::summarise($sessions, $muscleOf, [], '2026-09-02');

        expect($summary->sessions)->toBe(1);
        expect($summary->workingSets)->toBe(3);
        expect($summary->daysSinceLast)->toBe(1); // last real session = 09-01, not the future 09-05
    });

    it('leaves daysSinceLast null when the only done session is in the future', function (): void {
        $summary = StrengthWeeklyBrief::summarise(
            [muscuSession('2026-09-05', [performed('bench', [workingSet(60, 10)])])],
            ['bench' => MuscleGroup::CHEST],
            [],
            '2026-09-02',
        );

        expect($summary->sessions)->toBe(0);
        expect($summary->daysSinceLast)->toBeNull();
        expect($summary->hasData())->toBeFalse();
    });

    it('counts only done sessions in a week full of planned ones', function (): void {
        $summary = StrengthWeeklyBrief::summarise(
            [muscuSession('2026-09-01', [performed('bench', [workingSet(60, 10)])], WorkoutStatus::PLANNED)],
            ['bench' => MuscleGroup::CHEST],
            [],
            '2026-09-02',
        );

        expect($summary->sessions)->toBe(0);
        expect($summary->hasData())->toBeFalse();
    });

    it('excludes warm-up sets from working sets and tonnage', function (): void {
        $summary = StrengthWeeklyBrief::summarise(
            [muscuSession('2026-09-01', [performed('bench', [warmupSet(40, 10), workingSet(60, 10), workingSet(60, 10)])])],
            ['bench' => MuscleGroup::CHEST],
            [],
            '2026-09-02',
        );

        expect($summary->workingSets)->toBe(2);
        expect($summary->tonnageKg)->toBe(1200.0); // 2 × 60 × 10, warm-up excluded
    });

    it('excludes weight readings outside the week window from the average', function (): void {
        $weight = [
            new WeightEntry('2026-09-01', WeighMoment::MORNING, 72.0), // this week
            new WeightEntry('2026-06-01', WeighMoment::MORNING, 99.0), // far outside
        ];

        $summary = StrengthWeeklyBrief::summarise([], [], $weight, '2026-09-02');

        expect($summary->weightAvgKg)->toBe(72.0);
        expect($summary->weightPrevAvgKg)->toBeNull();
    });

    it('counts total working sets even when an exercise is missing from the catalog map', function (): void {
        // 'mystery' has no muscle mapping → its sets still count toward the total,
        // but contribute to no muscle in the balance.
        $summary = StrengthWeeklyBrief::summarise(
            [muscuSession('2026-09-01', [
                performed('bench', [workingSet(60, 10), workingSet(60, 10)]),
                performed('mystery', [workingSet(30, 12)]),
            ])],
            ['bench' => MuscleGroup::CHEST],
            [],
            '2026-09-02',
        );

        expect($summary->workingSets)->toBe(3);
        expect($summary->muscleBalance)->toBe([['muscle' => 'CHEST', 'label' => 'Pectoraux', 'sets' => 2]]);
    });
});
