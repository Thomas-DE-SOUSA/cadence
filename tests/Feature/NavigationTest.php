<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

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
