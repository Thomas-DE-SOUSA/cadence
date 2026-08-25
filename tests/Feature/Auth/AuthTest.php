<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/** Drop the in-memory user the global beforeEach signs in, to test as a guest. */
function asGuest(): void
{
    Auth::forgetGuards();
}

describe('Feature: Authentication & multi-account', function (): void {
    it('redirects guests to the login page', function (): void {
        asGuest();

        $this->get('/')->assertRedirect('/login');
        $this->get('/programme')->assertRedirect('/login');
        $this->get('/allures')->assertRedirect('/login');
    });

    it('registers a new account with its own private tenant', function (): void {
        asGuest();

        $this->post('/register', [
            'name' => 'Alice',
            'email' => 'alice@example.test',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertRedirect('/');

        $this->assertAuthenticated();

        $user = User::query()->where('email', 'alice@example.test')->firstOrFail();
        expect($user->tenant_id)->toStartWith('tenant-');
    });

    it('logs a user in with valid credentials and rejects bad ones', function (): void {
        asGuest();
        User::factory()->create([
            'email' => 'bob@example.test',
            'password' => Hash::make('secret1234'),
            'tenant_id' => 'tenant-bob',
        ]);

        $this->post('/login', ['email' => 'bob@example.test', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'bob@example.test', 'password' => 'secret1234'])
            ->assertRedirect('/');
        $this->assertAuthenticated();
    });

    it('isolates activity data between accounts', function (): void {
        $alice = User::factory()->create(['tenant_id' => 'tenant-alice']);
        $bob = User::factory()->create(['tenant_id' => 'tenant-bob']);

        // Alice records an activity — it must land under HER tenant, not the fallback.
        $this->actingAs($alice)->post('/activites', [
            'occurred_at' => '2026-08-19T18:00:00+00:00',
            'source' => 'MANUAL',
            'distance_meters' => 10010,
            'moving_seconds' => 2555,
            'elapsed_seconds' => 2653,
            'elevation_gain_meters' => 32,
        ])->assertRedirect();

        $this->assertDatabaseHas('activities', ['tenant_id' => 'tenant-alice']);
        $this->assertDatabaseMissing('activities', ['tenant_id' => 'tenant-bob']);

        // Bob's dashboard sees none of Alice's data.
        $this->actingAs($bob)->get('/')->assertInertia(
            fn ($page) => $page->component('History')->where('activities', [])
        );
    });
});
