<?php

declare(strict_types=1);

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Training\Infrastructure\Persistence\Eloquent\TrainingProgramModel;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: Programs', function (): void {
    it('creates a program, assigns a run and shows the evaluated objectives', function (): void {
        $this->post('/programme', [
            'name' => 'Prépa Odysséa 10K',
            'goal' => 'Passer sous 40 min',
            'target_race_name' => 'Odysséa Paris 10 km',
            'target_race_date' => '2026-10-04',
            'start_date' => '2026-08-20',
            'end_date' => '2026-10-04',
            'priority' => 'A',
            'objectives' => [
                ['type' => 'RACE_TIME', 'label' => '10 km sous 40:00', 'target_distance_meters' => 10000, 'target_seconds' => 2400],
                ['type' => 'SESSION_COUNT', 'label' => '20 séances', 'target_count' => 20],
            ],
        ])->assertRedirect();

        $this->assertDatabaseCount('training_programs', 1);
        $programId = (string) TrainingProgramModel::query()->value('id');

        $this->seed(ActivitySeeder::class);
        $activityId = (string) ActivityModel::query()->value('id');

        $this->post("/programme/{$programId}/assigner", ['activity_id' => $activityId])->assertRedirect();

        $this->get("/programme/{$programId}")->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('ProgramDetail')
                ->where('program.name', 'Prépa Odysséa 10K')
                ->has('program.objectives', 2)
                ->has('program.activities', 1),
        );
    });

    it('lists the tenant programs', function (): void {
        $this->post('/programme', [
            'name' => 'Bloc test',
            'start_date' => '2026-08-20',
            'priority' => 'B',
            'objectives' => [],
        ])->assertRedirect();

        $this->get('/programme')->assertInertia(
            fn (AssertableInertia $page) => $page->component('Programs')->has('programs', 1),
        );
    });
});
