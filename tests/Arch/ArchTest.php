<?php

declare(strict_types=1);

/*
 * Architecture tests make the hexagon self-enforcing. See docs/architecture/30-testing.md.
 * These run under the "Arch" test suite: `composer test:arch`.
 */

arch('production code declares strict types')
    ->expect('Cadence')
    ->toUseStrictTypes();

arch('no debugging leftovers ship')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('the shared domain kernel stays framework-agnostic')
    ->expect('Cadence\Shared\Domain')
    ->not->toUse('Illuminate');

arch('the clock and identifier ports stay framework-agnostic')
    ->expect(['Cadence\Shared\Clock\Clock', 'Cadence\Shared\Identifier\IdGenerator'])
    ->not->toUse('Illuminate');

// Every context's domain stays framework-free; every application layer avoids Eloquent.
arch('domain layers never depend on the framework')
    ->expect('Cadence\*\Domain')
    ->not->toUse('Illuminate');

arch('application layers never touch Eloquent')
    ->expect('Cadence\*\Application')
    ->not->toUse('Illuminate\Database\Eloquent');

// Activity context — explicit inward-only boundaries.
arch('the Activity domain depends on nothing outward')
    ->expect('Cadence\Activity\Domain')
    ->not->toUse([
        'Illuminate',
        'Cadence\Activity\Application',
        'Cadence\Activity\Infrastructure',
    ]);

arch('the Activity application never reaches into infrastructure')
    ->expect('Cadence\Activity\Application')
    ->not->toUse([
        'Illuminate\Database\Eloquent',
        'Cadence\Activity\Infrastructure',
    ]);

// Training context — explicit inward-only boundaries.
arch('the Training domain depends on nothing outward')
    ->expect('Cadence\Training\Domain')
    ->not->toUse([
        'Illuminate',
        'Cadence\Training\Application',
        'Cadence\Training\Infrastructure',
    ]);

arch('the Training application never reaches into infrastructure')
    ->expect('Cadence\Training\Application')
    ->not->toUse([
        'Illuminate\Database\Eloquent',
        'Cadence\Training\Infrastructure',
    ]);

// Coaching context — explicit inward-only boundaries.
arch('the Coaching domain depends on nothing outward')
    ->expect('Cadence\Coaching\Domain')
    ->not->toUse([
        'Illuminate',
        'Cadence\Coaching\Application',
        'Cadence\Coaching\Infrastructure',
    ]);

arch('the Coaching application never reaches into infrastructure')
    ->expect('Cadence\Coaching\Application')
    ->not->toUse([
        'Illuminate\Database\Eloquent',
        'Cadence\Coaching\Infrastructure',
    ]);

// Strength context — explicit inward-only boundaries.
arch('the Strength domain depends on nothing outward')
    ->expect('Cadence\Strength\Domain')
    ->not->toUse([
        'Illuminate',
        'Cadence\Strength\Application',
        'Cadence\Strength\Infrastructure',
    ]);

arch('the Strength application never reaches into infrastructure')
    ->expect('Cadence\Strength\Application')
    ->not->toUse([
        'Illuminate\Database\Eloquent',
        'Cadence\Strength\Infrastructure',
    ]);
