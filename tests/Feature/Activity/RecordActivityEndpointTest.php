<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function activityPayload(): array
{
    return [
        'occurred_at' => '2026-08-19T18:00:00+00:00',
        'source' => 'MANUAL',
        'distance_meters' => 10010,
        'moving_seconds' => 2555,
        'elapsed_seconds' => 2653,
        'elevation_gain_meters' => 32,
        'splits' => [
            ['index' => 1, 'distance_meters' => 1001, 'duration_seconds' => 226, 'elevation_meters' => -10],
            ['index' => 2, 'distance_meters' => 1001, 'duration_seconds' => 194, 'elevation_meters' => 3],
            ['index' => 3, 'distance_meters' => 1001, 'duration_seconds' => 211, 'elevation_meters' => -3],
            ['index' => 4, 'distance_meters' => 1001, 'duration_seconds' => 273, 'elevation_meters' => -9],
            ['index' => 5, 'distance_meters' => 1001, 'duration_seconds' => 274, 'elevation_meters' => 3],
            ['index' => 6, 'distance_meters' => 1001, 'duration_seconds' => 269, 'elevation_meters' => 4],
            ['index' => 7, 'distance_meters' => 1001, 'duration_seconds' => 316, 'elevation_meters' => -7],
            ['index' => 8, 'distance_meters' => 1001, 'duration_seconds' => 299, 'elevation_meters' => 9],
            ['index' => 9, 'distance_meters' => 1001, 'duration_seconds' => 222, 'elevation_meters' => 0],
            ['index' => 10, 'distance_meters' => 1001, 'duration_seconds' => 264, 'elevation_meters' => 2],
        ],
        'best_efforts' => [
            ['label' => '5k', 'distance_meters' => 5000, 'duration_seconds' => 1160, 'is_personal_record' => true],
            ['label' => '10k', 'distance_meters' => 10000, 'duration_seconds' => 2621, 'is_personal_record' => true],
        ],
    ];
}

describe('Feature: Record activity endpoint', function (): void {
    it('records an activity and writes its outbox event', function (): void {
        $response = $this->postJson('/api/activities', activityPayload());

        $response->assertStatus(201)
            ->assertJsonStructure(['activity_id', 'average_pace_seconds_per_km']);

        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseHas('activities', ['tenant_id' => 'tenant-thomas', 'version' => 1]);
        $this->assertDatabaseHas('outbox_events', [
            'event_name' => 'activity.recorded',
            'aggregate_type' => 'activity',
            'published' => false,
            'version' => 1,
        ]);
    });

    it('records under the server-side tenant, never a client-supplied one', function (): void {
        $this->postJson('/api/activities', [...activityPayload(), 'tenant_id' => 'tenant-attacker'])
            ->assertStatus(201);

        // The forged tenant_id is ignored; the row belongs to the server tenant.
        $this->assertDatabaseHas('activities', ['tenant_id' => 'tenant-thomas']);
        $this->assertDatabaseMissing('activities', ['tenant_id' => 'tenant-attacker']);
    });

    it('rejects an invalid payload with 422', function (): void {
        $this->postJson('/api/activities', ['source' => 'INVALID'])
            ->assertStatus(422);
    });
});
