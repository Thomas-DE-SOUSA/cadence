<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\GenerateCycle;

final readonly class GenerateCycleInput
{
    public function __construct(
        public string $programId,
        public string $startDate,
        public int $weeks,
        public string $ressenti,
        public string $athletePaces = '',
        public string $athleteState = '',
    ) {
    }
}
