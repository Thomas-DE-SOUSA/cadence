<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Enum;

enum SessionType: string
{
    case EASY = 'EASY';
    case LONG = 'LONG';
    case THRESHOLD = 'THRESHOLD';
    case INTERVALS = 'INTERVALS';
    case RECOVERY = 'RECOVERY';
    case RACE_PACE = 'RACE_PACE';
    case RACE = 'RACE';
    case CROSS = 'CROSS';
    case REST = 'REST';
}
