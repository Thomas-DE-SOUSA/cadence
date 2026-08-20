<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\ValueObject;

use Cadence\Activity\Domain\Exception\ActivityErrorCode;
use Cadence\Activity\Domain\Exception\InvalidActivity;

/** Running pace, stored as seconds per kilometre. Always derived, never entered. */
final class Pace
{
    private function __construct(public readonly float $secondsPerKm)
    {
    }

    public static function fromDistanceAndDuration(Distance $distance, Duration $duration): self
    {
        if ($distance->meters <= 0) {
            throw new InvalidActivity(
                ActivityErrorCode::DISTANCE_MUST_BE_POSITIVE,
                'Cannot derive a pace from a non-positive distance.',
            );
        }

        return new self($duration->seconds / $distance->kilometers());
    }

    public static function fromStorage(float $secondsPerKm): self
    {
        return new self($secondsPerKm);
    }

    /** Formatted as m:ss/km. */
    public function format(): string
    {
        $rounded = (int) round($this->secondsPerKm);

        return sprintf('%d:%02d/km', intdiv($rounded, 60), $rounded % 60);
    }

    public function equals(self $other): bool
    {
        return abs($this->secondsPerKm - $other->secondsPerKm) < 0.0001;
    }
}
