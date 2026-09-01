<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\LogWeightEntry;

final readonly class LogWeightEntryInput
{
    public function __construct(
        public ?string $date,        // Y-m-d, null → today
        public string $moment,       // MORNING | EVENING
        public float $weightKg,
        public string $note = '',
    ) {
    }
}
