<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\ValueObject;

use Cadence\Activity\Domain\Exception\ActivityErrorCode;
use Cadence\Activity\Domain\Exception\InvalidActivity;

/** A positive distance, stored in whole metres. */
final class Distance
{
    private function __construct(public readonly int $meters)
    {
    }

    public static function fromMeters(int $meters): self
    {
        if ($meters <= 0) {
            throw new InvalidActivity(
                ActivityErrorCode::DISTANCE_MUST_BE_POSITIVE,
                "Distance must be positive, got {$meters} m.",
            );
        }

        return new self($meters);
    }

    /** Hydration from trusted storage — no validation. */
    public static function fromStorage(int $meters): self
    {
        return new self($meters);
    }

    public function kilometers(): float
    {
        return $this->meters / 1000;
    }

    public function equals(self $other): bool
    {
        return $this->meters === $other->meters;
    }
}
