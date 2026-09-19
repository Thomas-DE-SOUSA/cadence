<?php

declare(strict_types=1);

use Cadence\Strength\Infrastructure\Persistence\Eloquent\StrengthProfileModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: Strength profile', function (): void {
    it('renders the profile form with option lists', function (): void {
        $this->get('/strength/profile')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('StrengthProfile')
                ->has('options.goals')
                ->has('options.muscles')
                ->where('profile.exists', false),
        );
    });

    it('saves the profile and drives the progression goal', function (): void {
        $this->post('/strength/profile', [
            'goal' => 'STRENGTH',
            'level' => 'ADVANCED',
            'bodyweightKg' => 78.5,
            'weeklyFrequency' => 4,
            'split' => 'PPL',
            'equipment' => 'FULL_GYM',
            'priorities' => ['QUADS', 'BACK'],
            'limitations' => ['SHOULDERS'],
            'note' => 'Ménager l’épaule droite',
        ])->assertRedirect('/strength/progression');

        $row = StrengthProfileModel::query()->where('tenant_id', 'tenant-thomas')->first();
        expect($row->goal)->toBe('STRENGTH');
        expect($row->bodyweight_kg)->toEqual(78.5);
        expect($row->priorities)->toBe(['QUADS', 'BACK']);

        $this->get('/strength/progression')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('StrengthProgression')
                ->where('goal', 'STRENGTH')
                ->where('hasProfile', true)
                ->has('weekly', 8)
                ->has('muscleVolume')
                ->has('records'),
        );
    });

    it('rejects an out-of-range bodyweight', function (): void {
        $this->post('/strength/profile', [
            'goal' => 'GENERAL',
            'level' => 'INTERMEDIATE',
            'bodyweightKg' => 5,
            'weeklyFrequency' => 3,
            'split' => 'FREE',
            'equipment' => 'HOME',
        ])->assertSessionHasErrors('bodyweightKg');
    });
});
