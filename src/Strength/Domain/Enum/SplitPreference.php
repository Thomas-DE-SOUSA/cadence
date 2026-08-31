<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Enum;

enum SplitPreference: string
{
    case FULL_BODY = 'FULL_BODY';
    case UPPER_LOWER = 'UPPER_LOWER';
    case PPL = 'PPL';
    case FREE = 'FREE';

    public function label(): string
    {
        return match ($this) {
            self::FULL_BODY => 'Full-body',
            self::UPPER_LOWER => 'Haut / Bas',
            self::PPL => 'Push / Pull / Legs',
            self::FREE => 'Libre',
        };
    }
}
