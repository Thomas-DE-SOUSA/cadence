<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Exception;

use Cadence\Shared\Domain\DomainException;
use Cadence\Training\Domain\ValueObject\ProgramId;

final class ProgramNotFound extends DomainException
{
    public static function withId(ProgramId $id): self
    {
        return new self(ProgramErrorCode::NOT_FOUND, "Program {$id->value} was not found.");
    }
}
