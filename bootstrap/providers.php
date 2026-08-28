<?php

use App\Providers\AppServiceProvider;
use Cadence\Activity\Infrastructure\ActivityServiceProvider;
use Cadence\Athlete\Infrastructure\AthleteServiceProvider;
use Cadence\Coaching\Infrastructure\CoachingServiceProvider;
use Cadence\Strength\Infrastructure\StrengthServiceProvider;
use Cadence\Training\Infrastructure\TrainingServiceProvider;

return [
    AppServiceProvider::class,
    ActivityServiceProvider::class,
    AthleteServiceProvider::class,
    TrainingServiceProvider::class,
    CoachingServiceProvider::class,
    StrengthServiceProvider::class,
];
