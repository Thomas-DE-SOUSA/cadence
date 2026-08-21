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

describe('Feature: Plan roadmap', function (): void {
    it('materialises the first cycle from a chosen plan', function (): void {
        $this->post('/programme', [
            'name' => 'Prépa Odysséa',
            'plan_key' => 'sub40-10k',
            'start_date' => '2026-08-24T00:00:00.000Z',
            'priority' => 'A',
            'objectives' => [],
        ])->assertRedirect();

        $programId = (string) TrainingProgramModel::query()->value('id');

        // Exactly one active cycle (the plan's first phase), 3 weeks of Fondation.
        expect(CycleModel::query()->count())->toBe(1);

        $this->get("/programme/{$programId}")->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('ProgramDetail')
                ->has('cycles', 1)
                ->where('cycles.0.name', 'Fondation')
                ->where('cycles.0.status', 'active')
                ->has('cycles.0.weeks', 3)
                ->has('roadmap', 4)
                ->where('roadmap.0.status', 'active')
                ->where('roadmap.1.status', 'locked')
                ->where('canGenerate', false),
        );
    });

    it('blocks generation until the current cycle is completed', function (): void {
        // Force the AI planner to fall back to the deterministic expert blueprint.
        $this->app->bind(CyclePlanner::class, fn (): CyclePlanner => new class implements CyclePlanner
        {
            public function plan(PlannerContext $context): PlannedCycle
            {
                throw new RuntimeException('AI unavailable');
            }
        });

        $this->post('/programme', [
            'name' => 'Prépa Odysséa',
            'plan_key' => 'sub40-10k',
            'start_date' => '2026-08-24T00:00:00.000Z',
            'priority' => 'A',
            'objectives' => [],
        ])->assertRedirect();

        $programId = (string) TrainingProgramModel::query()->value('id');

        // Generating before completing the active cycle is refused.
        $this->post("/programme/{$programId}/generer-cycle", ['start_date' => '', 'weeks' => 3, 'ressenti' => ''])
            ->assertRedirect();
        expect(CycleModel::query()->count())->toBe(1);

        $cycleId = (string) CycleModel::query()->value('id');
        $this->post("/programme/{$programId}/cycles/{$cycleId}/terminer")->assertRedirect();

        // Now the next phase materialises (fallback to the expert blueprint).
        $this->post("/programme/{$programId}/generer-cycle", ['start_date' => '', 'weeks' => 3, 'ressenti' => 'En forme.'])
            ->assertRedirect("/programme/{$programId}");

        expect(CycleModel::query()->count())->toBe(2);

        $this->get("/programme/{$programId}")->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('cycles.1.name', 'Développement')
                ->where('cycles.1.phaseIndex', 1)
                ->where('roadmap.0.status', 'done')
                ->where('roadmap.1.status', 'active'),
        );
    });
});
