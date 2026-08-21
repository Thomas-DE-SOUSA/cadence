<?php

declare(strict_types=1);

use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: Dashboard', function (): void {
    it('renders the gamified board with no activity yet', function (): void {
        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('History')
                ->where('stats.totalActivities', 0)
                ->has('achievements'),
        );
    });

    it('shows recent activities, records and streak', function (): void {
        $this->seed(ActivitySeeder::class);

        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('History')
                ->where('stats.totalActivities', 1)
                ->has('activities', 1)
                ->has('records')
                ->has('streak.days', 7),
        );
    });
});
