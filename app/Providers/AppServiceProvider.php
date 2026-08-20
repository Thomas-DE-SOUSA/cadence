<?php

namespace App\Providers;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\EventPublisher;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Clock\SystemClock;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Shared\Identifier\UuidGenerator;
use Cadence\Shared\Infrastructure\FixedTenantContext;
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
        $this->app->bind(
            TenantContext::class,
            fn (): FixedTenantContext => new FixedTenantContext(
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
