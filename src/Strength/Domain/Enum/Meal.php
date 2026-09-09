<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Enum;

/** The three meals a nutrition entry can belong to (no snack). */
enum Meal: string
{
    case Matin = 'matin';
    case Midi = 'midi';
    case Soir = 'soir';
}
