<?php

declare(strict_types=1);

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: Activity history', function (): void {
    it('lists the tenant activities', function (): void {
        $this->seed(ActivitySeeder::class);

        $this->get('/historique')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('History')
                ->has('activities', 1)
                ->where('activities.0.distanceMeters', 10010),
        );
    });

    it('shows an empty history when there is no activity', function (): void {
        $this->get('/historique')->assertInertia(
            fn (AssertableInertia $page) => $page->component('History')->has('activities', 0),
        );
    });
});

describe('Feature: Activity detail', function (): void {
    it('shows an activity with its splits and best efforts', function (): void {
        $this->seed(ActivitySeeder::class);
        $id = ActivityModel::query()->value('id');

        $this->get("/activites/{$id}")->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('ActivityDetail')
                ->where('activity.distanceMeters', 10010)
                ->has('activity.splits', 10),
        );
    });

    it('returns 404 for an unknown or cross-tenant activity', function (): void {
        $this->get('/activites/does-not-exist')->assertStatus(404);
    });
});
