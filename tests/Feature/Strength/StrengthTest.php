<?php

declare(strict_types=1);

use Cadence\Strength\Infrastructure\Persistence\Eloquent\ExerciseModel;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\StrengthSessionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: Muscu', function (): void {
    it('seeds a global exercise library visible on the editor', function (): void {
        expect(ExerciseModel::query()->whereNull('tenant_id')->count())->toBeGreaterThan(50);

        $this->get('/muscu/nouveau')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('MuscuSession')
                ->has('catalog')
                ->has('muscles')
                ->has('equipments'),
        );
    });

    it('logs a strength session and shows it with e1RM progression', function (): void {
        $exercise = ExerciseModel::query()->where('name', 'Squat (barre)')->first();

        $this->post('/muscu', [
            'date' => '2026-08-28',
            'title' => 'Legs',
            'durationSeconds' => 3600,
            'exercises' => [
                [
                    'exercise_id' => $exercise->id,
                    'name' => $exercise->name,
                    'sets' => [
                        ['weight_kg' => 60, 'reps' => 5, 'is_warmup' => true],
                        ['weight_kg' => 100, 'reps' => 5, 'rpe' => 8],
                        ['weight_kg' => 110, 'reps' => 3, 'rpe' => 9],
                    ],
                ],
            ],
        ])->assertRedirect('/muscu');

        expect(StrengthSessionModel::query()->where('tenant_id', 'tenant-thomas')->count())->toBe(1);

        $this->get('/muscu')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Muscu')
                ->where('sessions.0.title', 'Legs')
                ->where('sessions.0.totalSets', 2) // warm-up excluded
                ->where('sessions.0.volumeKg', 830) // 100*5 + 110*3
                ->where('progression.0.name', 'Squat (barre)')
                ->where('progression.0.bestE1rm', 121), // best of 100×5 (116.7) vs 110×3 (121.0)
        );
    });

    it('adds a custom exercise to the tenant library', function (): void {
        $before = ExerciseModel::query()->count();

        $this->post('/muscu/exercices', [
            'name' => 'Tirage poitrine prise serrée',
            'primaryMuscle' => 'BACK',
            'equipment' => 'CABLE',
        ])->assertRedirect();

        expect(ExerciseModel::query()->count())->toBe($before + 1);
        expect(ExerciseModel::query()->where('tenant_id', 'tenant-thomas')->where('is_custom', true)->count())->toBe(1);
    });

    it('rejects an invalid RPE', function (): void {
        $exercise = ExerciseModel::query()->first();

        $this->post('/muscu', [
            'date' => '2026-08-28',
            'exercises' => [
                ['exercise_id' => $exercise->id, 'name' => $exercise->name, 'sets' => [['weight_kg' => 80, 'reps' => 5, 'rpe' => 12]]],
            ],
        ])->assertSessionHasErrors('exercises.0.sets.0.rpe');
    });
});
