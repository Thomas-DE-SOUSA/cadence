<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Enum;

/** When a body-weight reading was taken. Morning (fasted) and evening pool into the weekly average. */
enum WeighMoment: string
{
    case MORNING = 'MORNING';
    case EVENING = 'EVENING';

    public function label(): string
    {
        return match ($this) {
            self::MORNING => 'Matin',
            self::EVENING => 'Soir',
        };
    }
}
