<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure;

use Cadence\Coaching\Domain\Port\AthleteHistoryProvider;
use Cadence\Coaching\Infrastructure\Provider\EloquentAthleteHistoryProvider;
use Illuminate\Support\ServiceProvider;

final class CoachingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AthleteHistoryProvider::class, EloquentAthleteHistoryProvider::class);
    }
}
