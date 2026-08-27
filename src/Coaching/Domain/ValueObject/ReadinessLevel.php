<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/** How ready the athlete is to train today, from their subjective check-in. */
enum ReadinessLevel: string
{
    case GREEN = 'green';
    case AMBER = 'amber';
    case RED = 'red';

    public function label(): string
    {
        return match ($this) {
            self::GREEN => 'Prêt',
            self::AMBER => 'Vigilance',
            self::RED => 'Prudence',
        };
    }
}
