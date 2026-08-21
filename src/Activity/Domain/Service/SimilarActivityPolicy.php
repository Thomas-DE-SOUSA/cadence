<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\Service;

/**
 * Decides when two activities are "the same run": same day, and distance and
 * moving time within a small tolerance. Pure — the range is applied by the query.
 */
final class SimilarActivityPolicy
{
    private const TOLERANCE = 0.02; // ±2%

    /**
     * Inclusive [min, max] bounds for a value under the tolerance.
     *
     * @return array{0: int, 1: int}
     */
    public static function range(int $value): array
    {
        return [
            (int) floor($value * (1 - self::TOLERANCE)),
            (int) ceil($value * (1 + self::TOLERANCE)),
        ];
    }

    /** The day portion (YYYY-MM-DD) of an ISO-8601 timestamp. */
    public static function day(string $occurredAtIso): string
    {
        return substr($occurredAtIso, 0, 10);
    }
}
