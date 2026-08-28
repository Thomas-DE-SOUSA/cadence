<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Enum;

/** Where a scheduled workout sits: planned on the agenda, or done. */
enum WorkoutStatus: string
{
    case PLANNED = 'PLANNED';
    case DONE = 'DONE';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Prévu',
            self::DONE => 'Fait',
        };
    }

    public function isDone(): bool
    {
        return $this === self::DONE;
    }
}
