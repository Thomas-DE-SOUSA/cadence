<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\Exception;

use Cadence\Shared\Domain\DomainException;

final class DuplicateActivity extends DomainException
{
    public static function onDay(string $day): self
    {
        return new self(
            ActivityErrorCode::ALREADY_EXISTS,
            "A very similar activity is already recorded on {$day}.",
        );
    }
}
