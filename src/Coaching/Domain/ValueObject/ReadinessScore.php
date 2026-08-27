<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/** The readiness verdict derived from a {@see WellnessCheckIn}. */
final readonly class ReadinessScore
{
    public function __construct(
        public int $score,          // 0..100
        public ReadinessLevel $level,
        public string $headline,
        public string $advice,
    ) {
    }
}
