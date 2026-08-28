<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Enum;

/** How an exercise is loaded — drives filtering and the logging UI defaults. */
enum Equipment: string
{
    case BARBELL = 'BARBELL';
    case DUMBBELL = 'DUMBBELL';
    case MACHINE = 'MACHINE';
    case CABLE = 'CABLE';
    case BODYWEIGHT = 'BODYWEIGHT';
    case KETTLEBELL = 'KETTLEBELL';
    case BANDS = 'BANDS';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::BARBELL => 'Barre',
            self::DUMBBELL => 'Haltères',
            self::MACHINE => 'Machine',
            self::CABLE => 'Poulie',
            self::BODYWEIGHT => 'Poids du corps',
            self::KETTLEBELL => 'Kettlebell',
            self::BANDS => 'Élastique',
            self::OTHER => 'Autre',
        };
    }

    /** Bodyweight moves default to no external load in the logger. */
    public function isBodyweight(): bool
    {
        return $this === self::BODYWEIGHT;
    }
}
