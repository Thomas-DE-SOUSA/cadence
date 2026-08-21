<?php

declare(strict_types=1);

use Cadence\Athlete\Infrastructure\Persistence\Eloquent\AthleteModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: Athlete profile', function (): void {
    it('shows the profile page with defaults when none exists yet', function (): void {
        $this->get('/profil')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Profile')
                ->where('derived.hasProfile', false)
                ->has('profile'),
        );
    });

    it('saves the profile and reflects it on the page', function (): void {
        $this->post('/profil', [
            'display_name' => 'Thomas',
            'birth_date' => '2000-03-15',
            'height_cm' => 180,
            'weight_kg' => 72,
            'resting_hr' => 48,
            'max_hr' => 190,
            'sessions_per_week' => 4,
            'weekly_volume_km' => 40,
            'preferred_days' => [1, 3, 5, 7],
            'race_name' => 'Odyssée',
            'race_date' => '2026-10-04',
            'goal_distance_km' => 10,
            'goal_time' => '40:00',
            'long_term_goal' => 'Un trail dans un an.',
            'session_reminders' => true,
        ])->assertRedirect('/profil');

        $this->assertDatabaseCount('athlete_profiles', 1);
        $stored = AthleteModel::query()->firstOrFail();
        expect($stored->profile['max_hr'])->toBe(190);
        expect($stored->profile['goal_distance_meters'])->toBe(10000);
        expect($stored->profile['goal_target_seconds'])->toBe(2400);

        $this->get('/profil')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Profile')
                ->where('derived.hasProfile', true)
                ->where('profile.displayName', 'Thomas')
                ->where('profile.maxHr', 190)
                ->where('derived.age', 26)
                ->has('derived.hrZones', 5),
        );
    });

    it('rejects an invalid heart rate', function (): void {
        $this->post('/profil', [
            'max_hr' => 90,
        ])->assertSessionHasErrors('max_hr');

        $this->assertDatabaseCount('athlete_profiles', 0);
    });
});
