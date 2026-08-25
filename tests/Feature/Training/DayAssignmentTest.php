<?php

declare(strict_types=1);

use Cadence\Training\Domain\Port\CyclePlanner;
use Cadence\Training\Domain\ValueObject\PlannedCycle;
use Cadence\Training\Domain\ValueObject\PlannerContext;
use Cadence\Training\Infrastructure\Persistence\Eloquent\CycleModel;
use Cadence\Training\Infrastructure\Persistence\Eloquent\TrainingProgramModel;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/** @return array<string, mixed> the Inertia props for a page GET */
function inertiaProps(string $url): array
{
    $props = [];
    test()->get($url)->assertInertia(function (AssertableInertia $page) use (&$props): void {
        $props = $page->toArray()['props'];
    });

    return $props;
}

/** @return array{0:string,1:string} program id and cycle id */
function programWithCycle(): array
{
    test()->post('/programme', [
        'name' => 'Prépa Odysséa',
        'plan_key' => 'sub40-10k',
        'start_date' => '2026-08-24T00:00:00.000Z',
        'priority' => 'A',
        'objectives' => [],
    ])->assertRedirect();

    return [
        (string) TrainingProgramModel::query()->value('id'),
        (string) CycleModel::query()->value('id'),
    ];
}

function linkedActivityOn(string $cycleId, string $date): ?string
{
    /** @var list<array<string, mixed>> $sessions */
    $sessions = CycleModel::query()->find($cycleId)?->sessions ?? [];
    foreach ($sessions as $s) {
        if (($s['date'] ?? null) === $date) {
            return $s['activity_id'] ?? null;
        }
    }

    return null;
}

describe('Feature: Day assignment', function (): void {
    it('links a run to a planned day and attaches it to the program', function (): void {
        [$programId, $cycleId] = programWithCycle();

        $this->seed(ActivitySeeder::class);
        $activityId = (string) \Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel::query()->value('id');

        $this->post("/programme/{$programId}/cycles/{$cycleId}/jour", [
            'date' => '2026-08-25',
            'activity_id' => $activityId,
        ])->assertRedirect();

        expect(linkedActivityOn($cycleId, '2026-08-25'))->toBe($activityId);

        // The run is now assigned to the program (for objective evaluation).
        $program = TrainingProgramModel::query()->find($programId);
        expect($program->assigned_activity_ids)->toContain($activityId);

        // A placed run leaves the "to place" pool...
        $available = inertiaProps("/programme/{$programId}")['available'];
        expect(array_column($available, 'id'))->not->toContain($activityId);

        // Unlink clears the day and returns the run to the pool.
        $this->post("/programme/{$programId}/cycles/{$cycleId}/jour", ['date' => '2026-08-25', 'activity_id' => null])
            ->assertRedirect();
        expect(linkedActivityOn($cycleId, '2026-08-25'))->toBeNull();

        $available = inertiaProps("/programme/{$programId}")['available'];
        expect(array_column($available, 'id'))->toContain($activityId);
    });

    it('auto-fills a training slot from any run in the week (flexible schedule)', function (): void {
        // The plan's day 0 is a rest day; the seeded run is on 2026-08-19.
        $this->post('/programme', [
            'name' => 'Prépa Odysséa',
            'plan_key' => 'sub40-10k',
            'start_date' => '2026-08-19T00:00:00.000Z',
            'priority' => 'A',
            'objectives' => [],
        ])->assertRedirect();

        $programId = (string) TrainingProgramModel::query()->value('id');
        $this->seed(ActivitySeeder::class);
        $activityId = (string) \Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel::query()->value('id');

        $props = inertiaProps("/programme/{$programId}");
        $week0 = $props['cycles'][0]['weeks'][0];

        // The run counts toward the week regardless of the exact weekday, filling
        // the first TRAINING slot (a rest day is never "done" by a run).
        $training = array_values(array_filter($week0['sessions'], fn (array $s): bool => $s['type'] !== 'REST'));
        expect($training[0]['actual']['id'])->toBe($activityId);
        expect($training[0]['manual'])->toBeFalse();
        expect($week0['done'])->toBe(1);

        // …and it is not offered in the "to place" pool.
        expect(array_column($props['available'], 'id'))->not->toContain($activityId);
    });

    it('reschedules a planned session to another day', function (): void {
        [$programId, $cycleId] = programWithCycle(); // sub40-10k, starts 2026-08-24

        // Day 1 (EASY) sits on 2026-08-25; move it to a free day next week.
        $this->post("/programme/{$programId}/cycles/{$cycleId}/jour/deplacer", [
            'from' => '2026-08-25',
            'to' => '2026-08-31',
        ])->assertRedirect();

        /** @var list<array<string, mixed>> $sessions */
        $sessions = CycleModel::query()->find($cycleId)->sessions;
        $dates = array_column($sessions, 'date');
        expect($dates)->toContain('2026-08-31');
        expect($dates)->not->toContain('2026-08-25');

        // The moved session also carries the new suggested day.
        $moved = collect($sessions)->firstWhere('date', '2026-08-31');
        expect($moved['suggested_date'])->toBe('2026-08-31');
    });

    it('preserves day-links when regenerating the cycle', function (): void {
        // Fall back to the deterministic blueprint (same dates), so the link survives.
        $this->app->bind(CyclePlanner::class, fn (): CyclePlanner => new class implements CyclePlanner
        {
            public function plan(PlannerContext $context): PlannedCycle
            {
                throw new RuntimeException('AI unavailable');
            }
        });

        [$programId, $cycleId] = programWithCycle();

        $this->seed(ActivitySeeder::class);
        $activityId = (string) \Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel::query()->value('id');

        $this->post("/programme/{$programId}/cycles/{$cycleId}/jour", ['date' => '2026-08-25', 'activity_id' => $activityId])
            ->assertRedirect();

        $this->post("/programme/{$programId}/cycles/{$cycleId}/refaire", ['ressenti' => 'En forme.'])
            ->assertRedirect("/programme/{$programId}");

        expect(CycleModel::query()->count())->toBe(1);
        expect(linkedActivityOn($cycleId, '2026-08-25'))->toBe($activityId);
    });
});
