<?php

declare(strict_types=1);

namespace Cadence\Athlete\Domain\Exception;

use Cadence\Shared\Domain\ErrorCode;

enum AthleteErrorCode: string implements ErrorCode
{
    case INVALID_BODY_METRIC = 'ATHLETE_INVALID_BODY_METRIC';
    case INVALID_HEART_RATE = 'ATHLETE_INVALID_HEART_RATE';
    case INVALID_AVAILABILITY = 'ATHLETE_INVALID_AVAILABILITY';
    case NOT_FOUND = 'ATHLETE_NOT_FOUND';
}
