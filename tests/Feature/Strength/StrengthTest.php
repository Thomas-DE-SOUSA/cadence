<?php

declare(strict_types=1);

use Cadence\Strength\Infrastructure\Persistence\Eloquent\ExerciseModel;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\StrengthSessionModel;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\WorkoutTemplateModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: Muscu', function (): void {
    it('renders the agenda week with 7 days', function (): void {
        $this->get('/muscu')->assertInertia(
            fn (AssertableInertia $page) => $page->component('MuscuAgenda')->has('days', 7)->has('templates')->has('weekLabel'),
        );
    });

    it('seeds a global exercise library visible in the template editor', function (): void {
        expect(ExerciseModel::query()->whereNull('tenant_id')->count())->toBeGreaterThan(50);

        $this->get('/muscu/seances/nouveau')->assertInertia(
            fn (AssertableInertia $page) => $page->component('MuscuTemplate')->has('catalog')->has('muscles')->has('equipments'),
        );
    });

    it('saves a séance template', function (): void {
        $exercise = ExerciseModel::query()->where('name', 'Squat (barre)')->first();

        $this->post('/muscu/seances', [
            'name' => 'Jambes',
            'exercises' => [
                ['exercise_id' => $exercise->id, 'name' => $exercise->name, 'sets' => [['weight_kg' => 100, 'reps' => 5]]],
            ],
        ])->assertRedirect('/muscu/seances');

        expect(WorkoutTemplateModel::query()->where('tenant_id', 'tenant-thomas')->where('name', 'Jambes')->count())->toBe(1);
    });

    it('logs a done session and shows it in progression', function (): void {
        $exercise = ExerciseModel::query()->where('name', 'Squat (barre)')->first();

        $this->post('/muscu/agenda', [
            'date' => '2026-08-28',
            'title' => 'Legs',
            'status' => 'DONE',
            'exercises' => [
                ['exercise_id' => $exercise->id, 'name' => $exercise->name, 'sets' => [
                    ['weight_kg' => 60, 'reps' => 5, 'is_warmup' => true],
                    ['weight_kg' => 110, 'reps' => 3, 'rpe' => 9],
                ]],
            ],
        ])->assertRedirect('/muscu');

        expect(StrengthSessionModel::query()->where('tenant_id', 'tenant-thomas')->where('status', 'DONE')->count())->toBe(1);

        $this->get('/muscu/progression')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('MuscuProgression')
                ->where('progression.0.name', 'Squat (barre)')
                ->where('progression.0.bestE1rm', 121),
        );
    });

    it('adds a custom exercise to the tenant library', function (): void {
        $before = ExerciseModel::query()->count();

        $this->post('/muscu/exercices', ['name' => 'Tirage prise serrée', 'primaryMuscle' => 'BACK', 'equipment' => 'CABLE'])
            ->assertRedirect();

        expect(ExerciseModel::query()->count())->toBe($before + 1);
    });

    it('rejects an invalid RPE', function (): void {
        $exercise = ExerciseModel::query()->first();

        $this->post('/muscu/agenda', [
            'date' => '2026-08-28',
            'exercises' => [
                ['exercise_id' => $exercise->id, 'name' => $exercise->name, 'sets' => [['weight_kg' => 80, 'reps' => 5, 'rpe' => 12]]],
            ],
        ])->assertSessionHasErrors('exercises.0.sets.0.rpe');
    });
});
