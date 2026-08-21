<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Event;

use Cadence\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final class ProgramCreated extends DomainEvent
{
    public const NAME = 'program.created';

    public function __construct(
        string $aggregateId,
        DateTimeImmutable $occurredAt,
        public readonly string $tenantId,
        public readonly string $name,
    ) {
        parent::__construct($aggregateId, $occurredAt);
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'name' => $this->name];
    }
}
