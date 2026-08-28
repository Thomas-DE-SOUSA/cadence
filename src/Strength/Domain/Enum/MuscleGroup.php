<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Enum;

/** Primary muscle worked — used to group the library and (later) balance volume. */
enum MuscleGroup: string
{
    case CHEST = 'CHEST';
    case BACK = 'BACK';
    case SHOULDERS = 'SHOULDERS';
    case BICEPS = 'BICEPS';
    case TRICEPS = 'TRICEPS';
    case FOREARMS = 'FOREARMS';
    case QUADS = 'QUADS';
    case HAMSTRINGS = 'HAMSTRINGS';
    case GLUTES = 'GLUTES';
    case CALVES = 'CALVES';
    case CORE = 'CORE';
    case FULL_BODY = 'FULL_BODY';

    public function label(): string
    {
        return match ($this) {
            self::CHEST => 'Pectoraux',
            self::BACK => 'Dos',
            self::SHOULDERS => 'Épaules',
            self::BICEPS => 'Biceps',
            self::TRICEPS => 'Triceps',
            self::FOREARMS => 'Avant-bras',
            self::QUADS => 'Quadriceps',
            self::HAMSTRINGS => 'Ischios',
            self::GLUTES => 'Fessiers',
            self::CALVES => 'Mollets',
            self::CORE => 'Gainage / abdos',
            self::FULL_BODY => 'Corps entier',
        };
    }
}
