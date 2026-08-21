<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Exception;

use Cadence\Shared\Domain\ErrorCode;

enum ProgramErrorCode: string implements ErrorCode
{
    case NAME_REQUIRED = 'PROGRAM_NAME_REQUIRED';
    case NOT_FOUND = 'PROGRAM_NOT_FOUND';
    case CYCLE_GENERATION_NOT_ALLOWED = 'CYCLE_GENERATION_NOT_ALLOWED';
}
