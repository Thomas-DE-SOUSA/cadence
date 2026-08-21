<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Exception;

use Cadence\Shared\Domain\DomainException;

final class DayContextMissing extends DomainException
{
    public static function forDay(string $programId, string $date): self
    {
        return new self(
            CoachingErrorCode::DAY_CONTEXT_MISSING,
            "No planned day {$date} for program {$programId}.",
        );
    }
}
