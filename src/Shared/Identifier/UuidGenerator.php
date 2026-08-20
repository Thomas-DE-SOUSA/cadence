<?php

declare(strict_types=1);

namespace Cadence\Shared\Identifier;

use Ramsey\Uuid\Uuid;

final class UuidGenerator implements IdGenerator
{
    public function generate(): string
    {
        return Uuid::uuid7()->toString();
    }
}
