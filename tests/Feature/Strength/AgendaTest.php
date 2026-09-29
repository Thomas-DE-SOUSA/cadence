<?php

declare(strict_types=1);

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Cadence\Strength\Application\UseCase\LogStrengthSession\LogStrengthSessionInput;
use Cadence\Strength\Application\UseCase\LogStrengthSession\LogStrengthSessionUseCase;
use Cadence\Strength\Application\UseCase\SaveWorkoutTemplate\SaveWorkoutTemplateInput;
use Cadence\Strength\Application\UseCase\SaveWorkoutTemplate\SaveWorkoutTemplateUseCase;
use Cadence\Strength\Application\UseCase\ScheduleWorkout\ScheduleWorkoutInput;
use Cadence\Strength\Application\UseCase\ScheduleWorkout\ScheduleWorkoutUseCase;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Service\OneRepMaxCalculator;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\StrengthSessionModel;
use Cadence\Strength\Infrastructure\Read\StrengthView;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ctx(): ExecutionContext
{
    return new ExecutionContext(TenantId::fromString('tenant-thomas'));
}

function ctxFor(string $tenant): ExecutionContext
{
    return new ExecutionContext(TenantId::fromString($tenant));
}

describe('Feature: Strength schedule (template → plan → done)', function (): void {
    it('creates a template, schedules it as PLANNED, then marks it DONE and tracks progression', function (): void {
        // 1. Save a reusable template.
        $templateId = app(SaveWorkoutTemplateUseCase::class)->execute(
            new SaveWorkoutTemplateInput(null, 'Push A', [
                ['exercise_id' => 'bench', 'name' => 'Développé couché', 'sets' => [
                    ['weight_kg' => 80, 'reps' => 8],
                    ['weight_kg' => 80, 'reps' => 8],
                ]],
            ]),
            ctx(),
        );

        // 2. Place it on a day → a PLANNED session, pre-filled from the template.
        $sessionId = app(ScheduleWorkoutUseCase::class)->execute(new ScheduleWorkoutInput($templateId, '2026-09-01'), ctx());

        $planned = StrengthSessionModel::query()->find($sessionId);
        expect($planned->status)->toBe('PLANNED');
        expect($planned->template_id)->toBe($templateId);
        expect($planned->title)->toBe('Push A');

        // Progression ignores planned sessions.
        $repo = app(StrengthSessionRepository::class);
        expect(StrengthView::progression($repo->forTenant(ctx()->tenant), new OneRepMaxCalculator()))->toBe([]);

        // 3. Do it: adjust actuals + mark DONE (keeps the same id + template link).
        app(LogStrengthSessionUseCase::class)->execute(
            new LogStrengthSessionInput($sessionId, '2026-09-01', 'Push A', '', null, [
                ['exercise_id' => 'bench', 'name' => 'Développé couché', 'sets' => [
                    ['weight_kg' => 82.5, 'reps' => 8],
                    ['weight_kg' => 82.5, 'reps' => 7],
                ]],
            ], 'DONE', $templateId),
            ctx(),
        );

        expect(StrengthSessionModel::query()->where('tenant_id', 'tenant-thomas')->count())->toBe(1);
        expect(StrengthSessionModel::query()->find($sessionId)->status)->toBe('DONE');

        $progression = StrengthView::progression($repo->forTenant(ctx()->tenant), new OneRepMaxCalculator());
        expect($progression)->toHaveCount(1);
        expect($progression[0]['name'])->toBe('Développé couché');
    });

    it('lists templates for the tenant', function (): void {
        app(SaveWorkoutTemplateUseCase::class)->execute(new SaveWorkoutTemplateInput(null, 'Jambes', []), ctx());
        app(SaveWorkoutTemplateUseCase::class)->execute(new SaveWorkoutTemplateInput(null, 'Pull A', []), ctx());

        $templates = app(\Cadence\Strength\Domain\Port\WorkoutTemplateRepository::class)->forTenant(ctx()->tenant);
        expect($templates)->toHaveCount(2);
    });

    it('carries forward the last performed weights when the template is scheduled again', function (): void {
        $templateId = app(SaveWorkoutTemplateUseCase::class)->execute(
            new SaveWorkoutTemplateInput(null, 'Push A', [
                ['exercise_id' => 'bench', 'name' => 'Développé couché', 'sets' => [['weight_kg' => 80, 'reps' => 8]]],
            ]),
            ctx(),
        );

        // Plan it, then actually do it heavier (82.5 kg) and mark DONE.
        $first = app(ScheduleWorkoutUseCase::class)->execute(new ScheduleWorkoutInput($templateId, '2026-09-01'), ctx());
        app(LogStrengthSessionUseCase::class)->execute(
            new LogStrengthSessionInput($first, '2026-09-01', 'Push A', '', null, [
                ['exercise_id' => 'bench', 'name' => 'Développé couché', 'sets' => [['weight_kg' => 82.5, 'reps' => 8]]],
            ], 'DONE', $templateId),
            ctx(),
        );

        // Schedule the same template again → the new PLANNED session starts from
        // the last performed weight (82.5), not the template's static 80.
        $second = app(ScheduleWorkoutUseCase::class)->execute(new ScheduleWorkoutInput($templateId, '2026-09-08'), ctx());

        $exercises = StrengthSessionModel::query()->find($second)->exercises;
        expect($exercises[0]['exercise_id'])->toBe('bench');
        expect((float) $exercises[0]['sets'][0]['weight_kg'])->toBe(82.5);
    });

    it('falls back to the template target for an exercise never performed', function (): void {
        $templateId = app(SaveWorkoutTemplateUseCase::class)->execute(
            new SaveWorkoutTemplateInput(null, 'Legs', [
                ['exercise_id' => 'squat', 'name' => 'Squat', 'sets' => [['weight_kg' => 100, 'reps' => 5]]],
            ]),
            ctx(),
        );

        $planned = app(ScheduleWorkoutUseCase::class)->execute(new ScheduleWorkoutInput($templateId, '2026-09-01'), ctx());

        $exercises = StrengthSessionModel::query()->find($planned)->exercises;
        expect((float) $exercises[0]['sets'][0]['weight_kg'])->toBe(100.0);
    });

    it('uses the latest training date, not just any DONE session', function (): void {
        $templateId = app(SaveWorkoutTemplateUseCase::class)->execute(
            new SaveWorkoutTemplateInput(null, 'Push A', [
                ['exercise_id' => 'bench', 'name' => 'Développé couché', 'sets' => [['weight_kg' => 60, 'reps' => 8]]],
            ]),
            ctx(),
        );

        // Two DONE sessions: an earlier date at 80, a later date at 85.
        $log = fn (string $date, float $kg) => app(LogStrengthSessionUseCase::class)->execute(
            new LogStrengthSessionInput(null, $date, 'Push A', '', null, [
                ['exercise_id' => 'bench', 'name' => 'Développé couché', 'sets' => [['weight_kg' => $kg, 'reps' => 8]]],
            ], 'DONE', $templateId),
            ctx(),
        );
        $log('2026-09-05', 85.0);
        $log('2026-09-01', 80.0); // logged after, but dated earlier

        $planned = app(ScheduleWorkoutUseCase::class)->execute(new ScheduleWorkoutInput($templateId, '2026-09-12'), ctx());
        $exercises = StrengthSessionModel::query()->find($planned)->exercises;
        expect((float) $exercises[0]['sets'][0]['weight_kg'])->toBe(85.0); // latest date wins, not last logged
    });

    it('never seeds a plan from another tenant’s performance', function (): void {
        // Another tenant benches heavy.
        app(LogStrengthSessionUseCase::class)->execute(
            new LogStrengthSessionInput(null, '2026-09-05', 'Push', '', null, [
                ['exercise_id' => 'bench', 'name' => 'Bench', 'sets' => [['weight_kg' => 999, 'reps' => 1]]],
            ], 'DONE', null),
            ctxFor('tenant-other'),
        );

        // Thomas has never done bench → falls back to his template target, not 999.
        $templateId = app(SaveWorkoutTemplateUseCase::class)->execute(
            new SaveWorkoutTemplateInput(null, 'Push A', [
                ['exercise_id' => 'bench', 'name' => 'Développé couché', 'sets' => [['weight_kg' => 80, 'reps' => 8]]],
            ]),
            ctx(),
        );
        $planned = app(ScheduleWorkoutUseCase::class)->execute(new ScheduleWorkoutInput($templateId, '2026-09-01'), ctx());

        $exercises = StrengthSessionModel::query()->find($planned)->exercises;
        expect((float) $exercises[0]['sets'][0]['weight_kg'])->toBe(80.0);
    });

    it('overlays achieved loads onto the template structure — keeps template warm-ups, drops RPE', function (): void {
        $templateId = app(SaveWorkoutTemplateUseCase::class)->execute(
            new SaveWorkoutTemplateInput(null, 'Push A', [
                ['exercise_id' => 'bench', 'name' => 'Développé couché', 'sets' => [
                    ['weight_kg' => 40, 'reps' => 10, 'is_warmup' => true],
                    ['weight_kg' => 80, 'reps' => 8],
                ]],
            ]),
            ctx(),
        );
        // Performed heavier on the working set, with an ad-hoc warm-up + RPE.
        app(LogStrengthSessionUseCase::class)->execute(
            new LogStrengthSessionInput(null, '2026-09-01', 'Push A', '', null, [
                ['exercise_id' => 'bench', 'name' => 'Développé couché', 'sets' => [
                    ['weight_kg' => 45, 'reps' => 10, 'is_warmup' => true],
                    ['weight_kg' => 82.5, 'reps' => 8, 'rpe' => 9],
                ]],
            ], 'DONE', $templateId),
            ctx(),
        );

        $planned = app(ScheduleWorkoutUseCase::class)->execute(new ScheduleWorkoutInput($templateId, '2026-09-08'), ctx());
        $sets = StrengthSessionModel::query()->find($planned)->exercises[0]['sets'];

        expect($sets)->toHaveCount(2);
        expect($sets[0]['is_warmup'])->toBeTrue();
        expect((float) $sets[0]['weight_kg'])->toBe(40.0); // warm-up load from the TEMPLATE, not the ad-hoc 45
        expect((float) $sets[1]['weight_kg'])->toBe(82.5); // working load from the performance
        expect($sets[1]['rpe'] ?? null)->toBeNull();       // per-session RPE not carried into a plan
    });

    it('keeps the template set count on re-schedule even after a partial session', function (): void {
        $templateId = app(SaveWorkoutTemplateUseCase::class)->execute(
            new SaveWorkoutTemplateInput(null, 'Abdos', [
                ['exercise_id' => 'abs', 'name' => 'Circuit abdominal', 'sets' => [
                    ['weight_kg' => 8, 'reps' => 15],
                    ['weight_kg' => 8, 'reps' => 15],
                    ['weight_kg' => 8, 'reps' => 15],
                ]],
            ]),
            ctx(),
        );

        // Only the first set is ticked done (heavier, 9 kg); the other two are left unticked.
        app(LogStrengthSessionUseCase::class)->execute(
            new LogStrengthSessionInput(null, '2026-09-01', 'Abdos', '', null, [
                ['exercise_id' => 'abs', 'name' => 'Circuit abdominal', 'sets' => [
                    ['weight_kg' => 9, 'reps' => 15, 'done' => true],
                    ['weight_kg' => 8, 'reps' => 15, 'done' => false],
                    ['weight_kg' => 8, 'reps' => 15, 'done' => false],
                ]],
            ], 'DONE', $templateId),
            ctx(),
        );

        // The logged session kept only the set actually done (1/3 → 1 set stored).
        $done = StrengthSessionModel::query()->where('tenant_id', 'tenant-thomas')->where('status', 'DONE')->first();
        expect($done->exercises[0]['sets'])->toHaveCount(1);
        expect((float) $done->exercises[0]['sets'][0]['weight_kg'])->toBe(9.0);

        // Re-scheduling still offers the template's 3 sets, seeded at the achieved 9 kg.
        $planned = app(ScheduleWorkoutUseCase::class)->execute(new ScheduleWorkoutInput($templateId, '2026-09-08'), ctx());
        $sets = StrengthSessionModel::query()->find($planned)->exercises[0]['sets'];

        expect($sets)->toHaveCount(3);
        expect(array_map(static fn (array $s): float => (float) $s['weight_kg'], $sets))->toBe([9.0, 9.0, 9.0]);
    });
});
