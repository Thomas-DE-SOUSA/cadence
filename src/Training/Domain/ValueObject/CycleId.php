<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\ValueObject;

use Cadence\Shared\Identifier\IdGenerator;
use InvalidArgumentException;

final class CycleId
{
    private function __construct(public readonly string $value)
    {
    }

    public static function generate(IdGenerator $ids): self
    {
        return new self($ids->generate());
    }

    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Cycle id cannot be empty.');
        }

        return new self($value);
    }
}
