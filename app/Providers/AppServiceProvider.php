<?php

namespace App\Providers;

use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Clock\SystemClock;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Shared\Identifier\UuidGenerator;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
