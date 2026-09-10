<?php

declare(strict_types=1);

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Cadence\Strength\Application\UseCase\LogStrengthSession\LogStrengthSessionInput;
use Cadence\Strength\Application\UseCase\LogStrengthSession\LogStrengthSessionUseCase;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Service\OneRepMaxCalculator;
use Cadence\Strength\Infrastructure\Read\StrengthView;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function progCtx(): ExecutionContext
{
    return new ExecutionContext(TenantId::fromString('tenant-thomas'));
}

/** @param list<array{id:string,name:string,sets:list<array<string,mixed>>}> $exercises */
function logDone(string $date, array $exercises): void
{
    $payload = array_map(static fn (array $e): array => [
        'exercise_id' => $e['id'],
        'name' => $e['name'],
        'sets' => $e['sets'],
    ], $exercises);

    app(LogStrengthSessionUseCase::class)->execute(
        new LogStrengthSessionInput(null, $date, 'Séance', '', null, $payload, 'DONE', null),
        progCtx(),
    );
}

describe('Feature: progression by exercise (position + reps + e1RM)', function (): void {
    it('captures position, top reps and e1RM per session, honouring the performed order', function (): void {
        // Session 1: bench 1st (80×8), row 2nd (60×10).
        logDone('2026-09-01', [
            ['id' => 'bench', 'name' => 'Développé couché', 'sets' => [['weight_kg' => 80, 'reps' => 8]]],
            ['id' => 'row', 'name' => 'Rowing', 'sets' => [['weight_kg' => 60, 'reps' => 10]]],
        ]);
        // Session 2: reordered — row 1st, bench 2nd (82.5×6).
        logDone('2026-09-08', [
            ['id' => 'row', 'name' => 'Rowing', 'sets' => [['weight_kg' => 62.5, 'reps' => 10]]],
            ['id' => 'bench', 'name' => 'Développé couché', 'sets' => [['weight_kg' => 82.5, 'reps' => 6]]],
        ]);

        $sessions = app(StrengthSessionRepository::class)->forTenant(progCtx()->tenant, 400);
        $prog = StrengthView::progression($sessions, new OneRepMaxCalculator());

        $bench = collect($prog)->firstWhere('exerciseId', 'bench');
        expect($bench)->not->toBeNull();
        expect($bench['series'])->toHaveCount(2);

        // Oldest first: 80×8 as 1st exercise, then 82.5×6 as 2nd (reordered).
        expect($bench['series'][0])->toMatchArray([
            'date' => '2026-09-01', 'topWeight' => 80.0, 'topReps' => 8, 'position' => 1, 'totalExercises' => 2, 'e1rm' => 101,
        ]);
        expect($bench['series'][1])->toMatchArray([
            'date' => '2026-09-08', 'topWeight' => 82.5, 'topReps' => 6, 'position' => 2, 'totalExercises' => 2, 'e1rm' => 99,
        ]);

        // e1RM sees that 80×8 (101) actually beats the heavier-but-fewer 82.5×6 (99).
        expect($bench['bestE1rm'])->toBe(101);
    });

    it('picks the best working set by e1RM for the data point', function (): void {
        logDone('2026-09-02', [
            ['id' => 'squat', 'name' => 'Squat', 'sets' => [
                ['weight_kg' => 100, 'reps' => 3],  // e1RM = 110
                ['weight_kg' => 90, 'reps' => 8],   // e1RM = 114 → this one wins
            ]],
        ]);

        $prog = StrengthView::progression(app(StrengthSessionRepository::class)->forTenant(progCtx()->tenant, 400), new OneRepMaxCalculator());
        $squat = collect($prog)->firstWhere('exerciseId', 'squat');

        expect($squat['series'][0])->toMatchArray(['topWeight' => 90.0, 'topReps' => 8, 'e1rm' => 114]);
    });
});
