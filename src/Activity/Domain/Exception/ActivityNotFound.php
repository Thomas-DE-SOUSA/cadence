<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\Exception;

use Cadence\Activity\Domain\ValueObject\ActivityId;
use Cadence\Shared\Domain\DomainException;

final class ActivityNotFound extends DomainException
{
    public static function withId(ActivityId $id): self
    {
        return new self(
            ActivityErrorCode::NOT_FOUND,
            "Activity {$id->value} was not found.",
        );
    }
}
