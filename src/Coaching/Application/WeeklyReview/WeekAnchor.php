<?php

declare(strict_types=1);

namespace Cadence\Coaching\Application\WeeklyReview;

use Cadence\Shared\Clock\Clock;
use DateTimeImmutable;
use Throwable;

/**
 * Resolves a raw request date to the Monday (Y-m-d) of its week — the single
 * place week-anchoring lives, so the controllers and the service can't drift.
 * A missing or non-`Y-m-d` value falls back to the current week (via the Clock).
 */
final class WeekAnchor
{
    public static function monday(?string $raw, Clock $clock): string
    {
        if ($raw !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            try {
                return (new DateTimeImmutable($raw))->modify('monday this week')->format('Y-m-d');
            } catch (Throwable) {
                // fall through to the current week
            }
        }

        return $clock->now()->modify('monday this week')->format('Y-m-d');
    }
}
