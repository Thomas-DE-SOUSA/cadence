<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\RegenerateCycle;

final readonly class RegenerateCycleInput
{
    public function __construct(
        public string $programId,
        public string $cycleId,
        public string $ressenti = '',
        public string $startDate = '',
    ) {
    }
}
