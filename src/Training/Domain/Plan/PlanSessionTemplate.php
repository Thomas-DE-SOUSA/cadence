<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Plan;

use Cadence\Training\Domain\Enum\SessionType;

/**
 * One recurring session in a weekly training pattern. `dayInWeek` is 0 (Monday)
 * through 6 (Sunday); paces are in seconds per kilometre.
 */
final readonly class PlanSessionTemplate
{
    public function __construct(
        public int $dayInWeek,
        public SessionType $type,
        public string $title,
        public string $description,
        public ?int $targetDistanceMeters = null,
        public ?int $targetDurationSeconds = null,
        public ?int $targetPaceSecondsPerKm = null,
    ) {
    }
}
