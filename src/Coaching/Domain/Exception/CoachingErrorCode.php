<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Exception;

use Cadence\Shared\Domain\ErrorCode;

enum CoachingErrorCode: string implements ErrorCode
{
    case CONVERSATION_NOT_FOUND = 'COACHING_CONVERSATION_NOT_FOUND';
    case DAY_CONTEXT_MISSING = 'COACHING_DAY_CONTEXT_MISSING';
}
