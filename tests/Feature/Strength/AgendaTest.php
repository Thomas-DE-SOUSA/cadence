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

describe('Feature: Muscu agenda (template → plan → done)', function (): void {
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
});
