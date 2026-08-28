<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Exception;

use Cadence\Shared\Domain\DomainException;

final class TemplateNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(StrengthErrorCode::TEMPLATE_NOT_FOUND, "Workout template {$id} was not found.");
    }
}
