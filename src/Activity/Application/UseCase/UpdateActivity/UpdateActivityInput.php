<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\UpdateActivity;

final readonly class UpdateActivityInput
{
    public function __construct(
        public string $activityId,
        public string $occurredAt,
        public int $distanceMeters,
        public int $movingSeconds,
        public int $elapsedSeconds,
        public int $elevationGainMeters,
    ) {
    }
}
