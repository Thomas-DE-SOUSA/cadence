<?php

declare(strict_types=1);

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Feature: Update activity endpoint', function (): void {
    it('updates an activity and redirects to its detail', function (): void {
        $this->seed(ActivitySeeder::class);
        $id = (string) ActivityModel::query()->value('id');

        $response = $this->put("/activites/{$id}", [
            'occurred_at' => '2026-08-20T10:00:00+00:00',
            'distance_meters' => 10010,
            'moving_seconds' => 2500,
            'elapsed_seconds' => 2600,
            'elevation_gain_meters' => 40,
            'splits' => [],
            'best_efforts' => [],
        ]);

        $response->assertRedirect("/activites/{$id}");
        $this->assertDatabaseHas('activities', [
            'id' => $id,
            'version' => 2,
            'moving_seconds' => 2500,
            'elevation_gain_meters' => 40,
        ]);
    });

    it('deletes an activity and redirects to the history', function (): void {
        $this->seed(ActivitySeeder::class);
        $id = (string) ActivityModel::query()->value('id');

        $this->delete("/activites/{$id}")->assertRedirect('/historique');

        $this->assertDatabaseMissing('activities', ['id' => $id, 'deleted_at' => null]);
    });
});
