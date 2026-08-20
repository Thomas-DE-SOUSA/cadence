<?php

declare(strict_types=1);

use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: Dashboard', function (): void {
    it('renders the dashboard with no activity yet', function (): void {
        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('activity', null),
        );
    });

    it('shows the latest activity with its splits and best efforts', function (): void {
        $this->seed(ActivitySeeder::class);

        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('activity.splits', 10)
                ->has('activity.bestEfforts', 3)
                ->where('activity.distanceMeters', 10010),
        );
    });
});
