<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Enum;

enum CycleStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
}
