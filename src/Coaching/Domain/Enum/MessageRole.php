<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Enum;

enum MessageRole: string
{
    case ATHLETE = 'athlete';
    case COACH = 'coach';
}
