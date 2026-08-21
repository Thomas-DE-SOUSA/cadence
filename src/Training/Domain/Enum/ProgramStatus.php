<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Enum;

enum ProgramStatus: string
{
    case PLANNED = 'PLANNED';
    case ACTIVE = 'ACTIVE';
    case COMPLETED = 'COMPLETED';
    case ABANDONED = 'ABANDONED';
}
