<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Enum;

/** Race priority: A = peak goal, B = secondary, C = training/tune-up. */
enum ProgramPriority: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
}
