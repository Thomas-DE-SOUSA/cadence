<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/** The planned session being discussed, plus the run actually done that day. */
final readonly class PlannedDay
{
    public function __construct(
        public string $date,
        public string $type,
        public string $title,
        public string $description,
        public ?int $targetDistanceMeters,
        public ?int $targetDurationSeconds,
        public ?int $targetPaceSecondsPerKm,
        public ?string $actualSummary,
    ) {
    }
}
