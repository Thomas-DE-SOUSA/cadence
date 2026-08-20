<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\RecordActivity;

final readonly class BestEffortInput
{
    public function __construct(
        public string $label,
        public int $distanceMeters,
        public int $durationSeconds,
        public bool $isPersonalRecord,
    ) {
    }
}
