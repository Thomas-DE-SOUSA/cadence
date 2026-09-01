<?php

declare(strict_types=1);

use Cadence\Strength\Infrastructure\Persistence\Eloquent\WeightEntryModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: weight tracking', function (): void {
    it('logs morning and evening readings and averages the week on the Poids page', function (): void {
        $this->post('/muscu/poids', ['date' => '2026-08-31', 'moment' => 'MORNING', 'weightKg' => 72.0])
            ->assertRedirect('/muscu/poids');
        $this->post('/muscu/poids', ['date' => '2026-08-31', 'moment' => 'EVENING', 'weightKg' => 73.0]);

        expect(WeightEntryModel::query()->where('tenant_id', 'tenant-thomas')->count())->toBe(2);

        $this->get('/muscu/poids')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('MuscuWeight')
                ->where('weeks.0.weekStart', '2026-08-31')
                ->where('weeks.0.avgKg', 72.5)
                ->where('weeks.0.count', 2),
        );
    });

    it('overwrites the same day + moment instead of duplicating', function (): void {
        $this->post('/muscu/poids', ['date' => '2026-08-31', 'moment' => 'MORNING', 'weightKg' => 72.0]);
        $this->post('/muscu/poids', ['date' => '2026-08-31', 'moment' => 'MORNING', 'weightKg' => 71.5]);

        expect(WeightEntryModel::query()->where('tenant_id', 'tenant-thomas')->count())->toBe(1);
        expect((float) WeightEntryModel::query()->where('tenant_id', 'tenant-thomas')->value('weight_kg'))->toBe(71.5);
    });

    it('rejects an out-of-range weight', function (): void {
        $this->post('/muscu/poids', ['moment' => 'MORNING', 'weightKg' => 3])
            ->assertSessionHasErrors('weightKg');
    });
});
