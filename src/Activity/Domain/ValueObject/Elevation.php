<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\ValueObject;

/** A signed elevation value in whole metres (positive gain, negative loss). */
final class Elevation
{
    private function __construct(public readonly int $meters)
    {
    }

    public static function ofMeters(int $meters): self
    {
        return new self($meters);
    }

    public function equals(self $other): bool
    {
        return $this->meters === $other->meters;
    }
}
