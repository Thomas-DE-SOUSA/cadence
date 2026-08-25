<?php

namespace App\Providers;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\EventPublisher;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Clock\SystemClock;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Shared\Identifier\UuidGenerator;
use Cadence\Shared\Infrastructure\AuthTenantContext;
use Cadence\Shared\Infrastructure\LaravelEventPublisher;
use Cadence\Shared\Infrastructure\LogAuditTrail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Shared kernel ports. Each bounded context registers its own ports
        // in its dedicated ServiceProvider (see docs/architecture/10-backend.md).
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(IdGenerator::class, UuidGenerator::class);
        $this->app->bind(EventPublisher::class, LaravelEventPublisher::class);
        $this->app->bind(AuditTrail::class, LogAuditTrail::class);
        // Tenant is resolved from the authenticated user (each account owns a
        // private tenant). Bound per-resolution so it always reads the current
        // request's auth state.
        $this->app->bind(
            TenantContext::class,
            fn (): AuthTenantContext => new AuthTenantContext(
                $this->app->make(\Illuminate\Contracts\Auth\Factory::class),
                (string) config('cadence.default_tenant', 'tenant-thomas'),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
