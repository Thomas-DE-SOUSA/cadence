<?php

declare(strict_types=1);

use App\Models\User;

/*
 * Pest configuration. Feature tests use the full framework TestCase;
 * Unit tests (use-case level) stay POPO and rely on in-memory fakes.
 */

pest()->extend(Tests\TestCase::class)
    ->beforeEach(function (): void {
        // App routes sit behind `auth`. Act as an in-memory user (not persisted)
        // on the default test tenant so tenant-scoped assertions keep resolving
        // to 'tenant-thomas', and view tests that skip RefreshDatabase are unaffected.
        $user = (new User())->forceFill([
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.test',
            'tenant_id' => (string) config('cadence.default_tenant', 'tenant-thomas'),
        ]);

        $this->actingAs($user);
    })
    ->in('Feature');
