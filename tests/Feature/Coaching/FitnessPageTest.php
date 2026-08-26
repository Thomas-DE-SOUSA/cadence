<?php

declare(strict_types=1);

use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: Forme & charge page', function (): void {
    it('renders load, form series and the 80/20 zones once there are runs', function (): void {
        $this->seed(ActivitySeeder::class);

        $this->get('/forme')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Forme')
                ->where('load.hasData', true)
                ->has('load.series')
                ->has('load.zones.easy')
                ->has('load.acwr'),
        );
    });

    it('shows an empty state with no data', function (): void {
        $this->get('/forme')->assertInertia(
            fn (AssertableInertia $page) => $page->component('Forme')->where('load.hasData', false),
        );
    });
});
