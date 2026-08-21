<?php

declare(strict_types=1);

use Cadence\Training\Domain\Port\CyclePlanner;
use Cadence\Training\Domain\ValueObject\PlannedCycle;
use Cadence\Training\Domain\ValueObject\PlannedSessionData;
use Cadence\Training\Domain\ValueObject\PlannerContext;
use Cadence\Training\Infrastructure\Persistence\Eloquent\CycleModel;
use Cadence\Training\Infrastructure\Persistence\Eloquent\TrainingProgramModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: Generate cycle', function (): void {
    it('generates a cycle from a fake planner and renders it week by week', function (): void {
        // A deterministic planner standing in for the Claude adapter.
        $this->app->bind(CyclePlanner::class, fn (): CyclePlanner => new class implements CyclePlanner
        {
            public function plan(PlannerContext $context): PlannedCycle
            {
                return new PlannedCycle('C1 Fondation', 'Construire l\'endurance', [
                    new PlannedSessionData(0, 'EASY', 'Footing', '40 min souple', 8000, 2400, 300),
                    new PlannedSessionData(1, 'REST', 'Repos', 'Repos complet', null, null, null),
                    new PlannedSessionData(6, 'LONG', 'Sortie longue', '1h15', 14000, null, 315),
                    new PlannedSessionData(9, 'THRESHOLD', 'Seuil', '3x8 min', 10000, null, 255),
                ]);
            }
        });

        $this->post('/programme', [
            'name' => 'Prépa Odysséa',
            'goal' => 'Passer sous 40 min',
            'start_date' => '2026-08-25',
            'priority' => 'A',
            'objectives' => [],
        ])->assertRedirect();

        $programId = (string) TrainingProgramModel::query()->value('id');

        $this->post("/programme/{$programId}/generer-cycle", [
            'start_date' => '2026-08-25',
            'weeks' => 2,
            'ressenti' => 'En forme, pas de douleur.',
        ])->assertRedirect("/programme/{$programId}");

        $this->assertDatabaseCount('cycles', 1);

        $this->get("/programme/{$programId}")->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('ProgramDetail')
                ->has('cycles', 1)
                ->where('cycles.0.name', 'C1 Fondation')
                ->has('cycles.0.weeks', 2),
        );
    });

    it('validates the generation input', function (): void {
        $this->post('/programme', [
            'name' => 'Bloc',
            'start_date' => '2026-08-25',
            'priority' => 'B',
            'objectives' => [],
        ])->assertRedirect();

        $programId = (string) TrainingProgramModel::query()->value('id');

        // JSON so validation surfaces as a 422 body, independent of session flash.
        $this->postJson("/programme/{$programId}/generer-cycle", [
            'start_date' => 'not-a-date',
            'weeks' => 99,
        ])->assertStatus(422)->assertJsonValidationErrors(['start_date', 'weeks']);

        expect(CycleModel::query()->count())->toBe(0);
    });
});
