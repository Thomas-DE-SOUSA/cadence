<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Enum;

/** How an exercise beat its previous performance: a heavier top set, or more reps at the same load. */
enum ProgressionKind: string
{
    case WEIGHT = 'weight';
    case REPS = 'reps';
}
