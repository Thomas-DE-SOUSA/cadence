<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Enum;

enum ObjectiveType: string
{
    /** Cover a distance under a target time (e.g. 10 km under 40:00). */
    case RACE_TIME = 'RACE_TIME';

    /** Hold a pace or better over at least a distance (e.g. ≤ 4:00/km over 5 km). */
    case PACE_OVER_DISTANCE = 'PACE_OVER_DISTANCE';

    /** A single run of at least a distance (e.g. ≥ 15 km). */
    case LONGEST_RUN = 'LONGEST_RUN';

    /** Cumulative distance across assigned runs (e.g. ≥ 150 km). */
    case TOTAL_VOLUME = 'TOTAL_VOLUME';

    /** At least N assigned runs. */
    case SESSION_COUNT = 'SESSION_COUNT';
}
