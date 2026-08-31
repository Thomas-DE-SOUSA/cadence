<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Enum;

/** The athlete's main strength-training aim — drives progression emphasis, cycles and coach advice. */
enum StrengthGoal: string
{
    case GENERAL = 'GENERAL';
    case HYPERTROPHY = 'HYPERTROPHY';
    case STRENGTH = 'STRENGTH';
    case ENDURANCE = 'ENDURANCE';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'Général / entretien',
            self::HYPERTROPHY => 'Hypertrophie',
            self::STRENGTH => 'Force',
            self::ENDURANCE => 'Endurance-force',
        };
    }
}
