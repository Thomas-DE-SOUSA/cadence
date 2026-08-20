<?php

declare(strict_types=1);

namespace Cadence\Shared\Application;

use Cadence\Shared\Domain\DomainEvent;

/**
 * Publishes domain events after they have been durably stored (outbox).
 * Implementations are best-effort and MUST NOT throw: a publish failure never
 * rolls back an already-committed aggregate.
 */
interface EventPublisher
{
    /** @param list<DomainEvent> $events */
    public function publish(array $events): void;
}
