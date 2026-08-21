<?php

declare(strict_types=1);

namespace Cadence\Athlete\Domain\Event;

use Cadence\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final class AthleteProfileSaved extends DomainEvent
{
    public const NAME = 'athlete.profile_saved';

    public function __construct(
        string $aggregateId,
        DateTimeImmutable $occurredAt,
        public readonly string $tenantId,
    ) {
        parent::__construct($aggregateId, $occurredAt);
    }

    public function name(): string
    {
        return self::NAME;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'tenant_id' => $this->tenantId,
        ];
    }
}
