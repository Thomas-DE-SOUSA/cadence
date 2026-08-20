<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Cadence\Shared\Clock\Clock;
use DateTimeImmutable;

/** Deterministic clock for tests. */
final class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public static function at(string $iso): self
    {
        return new self(new DateTimeImmutable($iso));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(string $iso): void
    {
        $this->now = new DateTimeImmutable($iso);
    }
}
