<?php

use App\Providers\AppServiceProvider;
use Cadence\Activity\Infrastructure\ActivityServiceProvider;
use Cadence\Training\Infrastructure\TrainingServiceProvider;

return [
    AppServiceProvider::class,
    ActivityServiceProvider::class,
    TrainingServiceProvider::class,
];
