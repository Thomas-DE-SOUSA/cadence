<?php

declare(strict_types=1);

namespace Cadence\Athlete\Infrastructure;

use Cadence\Athlete\Domain\Port\AthleteRepository;
use Cadence\Athlete\Infrastructure\Persistence\Eloquent\EloquentAthleteRepository;
use Illuminate\Support\ServiceProvider;

final class AthleteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AthleteRepository::class, EloquentAthleteRepository::class);
    }
}
