<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/** One logged run, reduced to what fitness assessment needs. */
final readonly class PerformancePoint
{
    public function __construct(
        public int $distanceMeters,
        public int $movingSeconds,
        public string $occurredAt,
    ) {
    }
}
