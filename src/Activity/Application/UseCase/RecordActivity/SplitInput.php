<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\RecordActivity;

final readonly class SplitInput
{
    public function __construct(
        public int $index,
        public int $distanceMeters,
        public int $durationSeconds,
        public int $elevationMeters,
    ) {
    }
}
