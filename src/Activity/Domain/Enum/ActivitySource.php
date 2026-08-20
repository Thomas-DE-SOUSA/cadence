<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\Enum;

enum ActivitySource: string
{
    case MANUAL = 'MANUAL';
    case STRAVA = 'STRAVA';
}
