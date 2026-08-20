<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\Exception;

use Cadence\Shared\Domain\ErrorCode;

enum ActivityErrorCode: string implements ErrorCode
{
    case DISTANCE_MUST_BE_POSITIVE = 'ACTIVITY_DISTANCE_MUST_BE_POSITIVE';
    case DURATION_MUST_BE_POSITIVE = 'ACTIVITY_DURATION_MUST_BE_POSITIVE';
    case SPLITS_DISTANCE_MISMATCH = 'ACTIVITY_SPLITS_DISTANCE_MISMATCH';
    case NOT_FOUND = 'ACTIVITY_NOT_FOUND';
}
