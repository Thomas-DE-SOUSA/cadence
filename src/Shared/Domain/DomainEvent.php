<?php

declare(strict_types=1);

namespace Cadence\Shared\Domain;

use DateTimeImmutable;

/**
 * Base class for domain events. Payloads carry primitives, backed enums and
 * plain arrays only — never value objects or entities.
 */
abstract class DomainEvent
{
    public function __construct(
        public readonly string $aggregateId,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /** Dotted, lower-case, past tense — e.g. "activity.recorded". */
    abstract public function name(): string;

    /** @return array<string, mixed> */
    abstract public function payload(): array;
}
