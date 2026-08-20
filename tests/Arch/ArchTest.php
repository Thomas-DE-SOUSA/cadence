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

/*
 * Per-bounded-context boundaries — uncomment each as the context lands:
 *
 * arch('Training domain is pure')
 *     ->expect('Cadence\Training\Domain')
 *     ->not->toUse(['Illuminate', 'Cadence\Training\Application', 'Cadence\Training\Infrastructure']);
 *
 * arch('Training application never touches Eloquent')
 *     ->expect('Cadence\Training\Application')
 *     ->not->toUse('Illuminate\Database\Eloquent');
 *
 * arch('Training use cases are final and suffixed')
 *     ->expect('Cadence\Training\Application\UseCase')
 *     ->toBeFinal()
 *     ->toHaveSuffix('UseCase');
 */
