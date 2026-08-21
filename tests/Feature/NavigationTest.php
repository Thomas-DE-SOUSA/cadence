<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

describe('Feature: Section navigation', function (): void {
    it('renders each section shell', function (string $url, string $component): void {
        $this->get($url)->assertInertia(
            fn (AssertableInertia $page) => $page->component($component),
        );
    })->with([
        ['/progression', 'Progression'],
        ['/allures', 'Paces'],
        ['/profil', 'Profile'],
    ]);
});
