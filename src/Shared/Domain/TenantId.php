<?php

declare(strict_types=1);

namespace Cadence\Shared\Domain;

use InvalidArgumentException;

/** Cross-cutting tenant identity. Every query and aggregate is scoped by it. */
final class TenantId
{
    private function __construct(public readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Tenant id cannot be empty.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
