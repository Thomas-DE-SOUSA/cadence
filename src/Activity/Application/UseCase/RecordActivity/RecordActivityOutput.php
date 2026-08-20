<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\RecordActivity;

final readonly class RecordActivityOutput
{
    public function __construct(
        public string $activityId,
        public float $averagePaceSecondsPerKm,
    ) {
    }
}
