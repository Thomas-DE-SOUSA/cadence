<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\ValueObject;

use Cadence\Shared\Identifier\IdGenerator;
use InvalidArgumentException;

final class ActivityId
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
            throw new InvalidArgumentException('Activity id cannot be empty.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
