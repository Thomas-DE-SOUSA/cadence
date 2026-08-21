<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Exception;

use Cadence\Shared\Domain\DomainException;

final class CycleNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(ProgramErrorCode::NOT_FOUND, "Cycle {$id} was not found.");
    }
}
