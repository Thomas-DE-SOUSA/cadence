<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Enum;

enum GymAccess: string
{
    case FULL_GYM = 'FULL_GYM';
    case FREE_WEIGHTS = 'FREE_WEIGHTS';
    case HOME = 'HOME';

    public function label(): string
    {
        return match ($this) {
            self::FULL_GYM => 'Salle complète',
            self::FREE_WEIGHTS => 'Haltères + barre',
            self::HOME => 'Maison / poids du corps',
        };
    }
}
