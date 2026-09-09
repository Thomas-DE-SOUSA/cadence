<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\LogFood;

final readonly class LogFoodInput
{
    public function __construct(
        public string $text,         // free-text meal description
        public string $meal,         // matin | midi | soir
        public ?string $date = null, // Y-m-d, null → today
    ) {
    }
}
