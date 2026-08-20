<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Feature: Manual activity entry (web)', function (): void {
    it('records an activity from the form and redirects to its detail page', function (): void {
        $response = $this->post('/activites', [
            'occurred_at' => '2026-08-19T18:00:00+00:00',
            'source' => 'MANUAL',
            'distance_meters' => 10010,
            'moving_seconds' => 2555,
            'elapsed_seconds' => 2653,
            'elevation_gain_meters' => 32,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseHas('activities', ['tenant_id' => 'tenant-thomas']);
    });

    it('redirects back with errors on an invalid submission', function (): void {
        $this->from('/activites/nouvelle')
            ->post('/activites', ['source' => 'INVALID'])
            ->assertRedirect('/activites/nouvelle')
            ->assertSessionHasErrors(['distance_meters', 'moving_seconds']);

        $this->assertDatabaseCount('activities', 0);
    });
});
