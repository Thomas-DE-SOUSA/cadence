<?php

declare(strict_types=1);

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Cadence\Strength\Application\UseCase\LogStrengthSession\LogStrengthSessionInput;
use Cadence\Strength\Application\UseCase\LogStrengthSession\LogStrengthSessionUseCase;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\StrengthSessionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function reviewCtx(): ExecutionContext
{
    return new ExecutionContext(TenantId::fromString('tenant-thomas'));
}

/** @param list<array{exercise_id:string,name:string,sets:list<array<string,mixed>>}> $exercises */
function logSession(string $id, string $date, array $exercises): void
{
    app(LogStrengthSessionUseCase::class)->execute(
        new LogStrengthSessionInput($id, $date, 'Push', '', 3120, $exercises, 'DONE', null),
        reviewCtx(),
    );
}

describe('Feature: post-session review', function (): void {
    it('reports what beat last time — heavier load, more reps, and first-time exercises', function (): void {
        // Last time.
        logSession('sessA', '2026-09-01', [
            ['exercise_id' => 'bench', 'name' => 'Développé couché', 'sets' => [['weight_kg' => 60, 'reps' => 10]]],
            ['exercise_id' => 'ohp', 'name' => 'Développé militaire', 'sets' => [['weight_kg' => 40, 'reps' => 8]]],
        ]);
        // This session: bench heavier, ohp more reps at same load, squat brand-new.
        logSession('sessB', '2026-09-08', [
            ['exercise_id' => 'bench', 'name' => 'Développé couché', 'sets' => [['weight_kg' => 62.5, 'reps' => 10]]],
            ['exercise_id' => 'ohp', 'name' => 'Développé militaire', 'sets' => [['weight_kg' => 40, 'reps' => 10]]],
            ['exercise_id' => 'squat', 'name' => 'Squat', 'sets' => [['weight_kg' => 100, 'reps' => 5]]],
        ]);

        $this->get('/strength/schedule/sessB/review')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('StrengthSessionReview')
                ->where('session.id', 'sessB')
                ->where('session.exerciseCount', 3)
                ->where('session.volumeKg', 1525)          // 625 + 400 + 500
                ->where('session.previousVolumeKg', 920)    // sessA: 600 + 320
                ->has('progressions', 2)
                ->where('progressions.0.exerciseId', 'bench')
                ->where('progressions.0.kind', 'weight')
                ->where('progressions.0.weightDeltaKg', 2.5)
                ->where('progressions.1.exerciseId', 'ohp')
                ->where('progressions.1.kind', 'reps')
                ->where('progressions.1.repsDelta', 2)
                ->where('firstTimeCount', 1)   // squat
                ->where('stableCount', 0),
        );
    });

    it('drops unticked sets on completion and still detects the ticked progression', function (): void {
        // Reference: abs circuit at 8 kg last time.
        logSession('absA', '2026-09-01', [
            ['exercise_id' => 'abs', 'name' => 'Circuit abdominal', 'sets' => [['weight_kg' => 8, 'reps' => 15]]],
        ]);

        // Today: 3 planned sets, only the first ticked (at 9 kg); the rest left unticked.
        app(LogStrengthSessionUseCase::class)->execute(
            new LogStrengthSessionInput('absB', '2026-09-08', 'Abdos', '', 1200, [
                ['exercise_id' => 'abs', 'name' => 'Circuit abdominal', 'sets' => [
                    ['weight_kg' => 9, 'reps' => 15, 'done' => true],
                    ['weight_kg' => 8, 'reps' => 15, 'done' => false],
                    ['weight_kg' => 8, 'reps' => 15, 'done' => false],
                ]],
            ], 'DONE', null),
            reviewCtx(),
        );

        // The unticked sets are dropped from the log (1/3 → 1 set stored).
        expect(StrengthSessionModel::query()->find('absB')->exercises[0]['sets'])->toHaveCount(1);

        // ...and the +1 kg on the ticked set is recognised in the recap.
        $this->get('/strength/schedule/absB/review')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('StrengthSessionReview')
                ->has('progressions', 1)
                ->where('progressions.0.exerciseId', 'abs')
                ->where('progressions.0.kind', 'weight')
                ->where('progressions.0.weightDeltaKg', fn ($v): bool => (float) $v === 1.0),
        );
    });

    it('shows no progression the very first time an exercise is done', function (): void {
        logSession('solo', '2026-09-10', [
            ['exercise_id' => 'bench', 'name' => 'Développé couché', 'sets' => [['weight_kg' => 60, 'reps' => 10]]],
        ]);

        $this->get('/strength/schedule/solo/review')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('StrengthSessionReview')
                ->has('progressions', 0)
                ->where('session.previousVolumeKg', null)   // first ever session, nothing to compare
                ->where('firstTimeCount', 1),
        );
    });

    it('redirects to the review after a session is completed over HTTP', function (): void {
        $this->post('/strength/schedule', [
            'id' => null,
            'date' => '2026-09-12',
            'title' => 'Pull',
            'status' => 'DONE',
            'durationSeconds' => 2400,
            'exercises' => [
                ['exercise_id' => 'row', 'name' => 'Rowing', 'sets' => [['weight_kg' => 70, 'reps' => 8]]],
            ],
        ]);

        $id = StrengthSessionModel::query()->where('tenant_id', 'tenant-thomas')->value('id');
        expect($id)->not->toBeNull();

        // Re-post with the known id to assert the exact redirect target.
        $this->post('/strength/schedule', [
            'id' => $id,
            'date' => '2026-09-12',
            'title' => 'Pull',
            'status' => 'DONE',
            'durationSeconds' => 2400,
            'exercises' => [
                ['exercise_id' => 'row', 'name' => 'Rowing', 'sets' => [['weight_kg' => 72.5, 'reps' => 8]]],
            ],
        ])->assertRedirect("/strength/schedule/{$id}/review");
    });
});
