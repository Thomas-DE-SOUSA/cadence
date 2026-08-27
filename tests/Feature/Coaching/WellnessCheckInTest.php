<?php

declare(strict_types=1);

use Cadence\Coaching\Infrastructure\Persistence\Eloquent\WellnessCheckInModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: wellness check-in', function (): void {
    it('records today’s check-in and surfaces the readiness verdict on Forme', function (): void {
        $this->post('/forme/check-in', [
            'sleep' => 5,
            'energy' => 5,
            'legs' => 5,
            'motivation' => 5,
            'painLevel' => 0,
        ])->assertRedirect('/forme');

        expect(WellnessCheckInModel::query()->where('tenant_id', 'tenant-thomas')->count())->toBe(1);

        $this->get('/forme')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Forme')
                ->where('checkin.readiness.level', 'green')
                ->where('checkin.readiness.score', 100),
        );
    });

    it('replaces the same day’s check-in instead of duplicating it', function (): void {
        $payload = ['sleep' => 3, 'energy' => 3, 'legs' => 3, 'motivation' => 3, 'painLevel' => 0];
        $this->post('/forme/check-in', $payload);
        $this->post('/forme/check-in', [...$payload, 'sleep' => 4]);

        expect(WellnessCheckInModel::query()->where('tenant_id', 'tenant-thomas')->count())->toBe(1);
        expect(WellnessCheckInModel::query()->where('tenant_id', 'tenant-thomas')->value('sleep'))->toBe(4);
    });

    it('reads a limiting pain as a red readiness even with great sensations', function (): void {
        $this->post('/forme/check-in', [
            'sleep' => 5,
            'energy' => 5,
            'legs' => 5,
            'motivation' => 5,
            'painLevel' => 3,
            'painLocation' => 'genou',
        ])->assertRedirect('/forme');

        $this->get('/forme')->assertInertia(
            fn (AssertableInertia $page) => $page->where('checkin.readiness.level', 'red'),
        );
    });

    it('rejects out-of-range sensations', function (): void {
        $this->post('/forme/check-in', [
            'sleep' => 9,
            'energy' => 3,
            'legs' => 3,
            'motivation' => 3,
            'painLevel' => 0,
        ])->assertSessionHasErrors('sleep');
    });
});
