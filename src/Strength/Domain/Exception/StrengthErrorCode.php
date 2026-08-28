<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Exception;

use Cadence\Shared\Domain\ErrorCode;

enum StrengthErrorCode: string implements ErrorCode
{
    case TEMPLATE_NOT_FOUND = 'WORKOUT_TEMPLATE_NOT_FOUND';
    case SESSION_NOT_FOUND = 'STRENGTH_SESSION_NOT_FOUND';
}
