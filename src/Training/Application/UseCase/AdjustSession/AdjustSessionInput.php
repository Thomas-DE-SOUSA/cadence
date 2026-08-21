<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\AdjustSession;

final readonly class AdjustSessionInput
{
    public function __construct(
        public string $programId,
        public string $cycleId,
        public string $date,
        public string $type,
        public string $title,
        public string $description,
        public ?int $targetDistanceMeters = null,
        public ?int $targetDurationSeconds = null,
        public ?int $targetPaceSecondsPerKm = null,
    ) {
    }
}
