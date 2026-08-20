<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\ValueObject;

use Cadence\Activity\Domain\Exception\ActivityErrorCode;
use Cadence\Activity\Domain\Exception\InvalidActivity;

/** A positive duration, stored in whole seconds. */
final class Duration
{
    private function __construct(public readonly int $seconds)
    {
    }

    public static function fromSeconds(int $seconds): self
    {
        if ($seconds <= 0) {
            throw new InvalidActivity(
                ActivityErrorCode::DURATION_MUST_BE_POSITIVE,
                "Duration must be positive, got {$seconds} s.",
            );
        }

        return new self($seconds);
    }

    /** Hydration from trusted storage — no validation. */
    public static function fromStorage(int $seconds): self
    {
        return new self($seconds);
    }

    /** Formatted as h:mm:ss or m:ss. */
    public function format(): string
    {
        $h = intdiv($this->seconds, 3600);
        $m = intdiv($this->seconds % 3600, 60);
        $s = $this->seconds % 60;

        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $s)
            : sprintf('%d:%02d', $m, $s);
    }

    public function equals(self $other): bool
    {
        return $this->seconds === $other->seconds;
    }
}
