<?php

declare(strict_types=1);

namespace Cadence\Shared\Domain;

/**
 * Base class for aggregate roots. Records domain events raised by behaviour
 * methods; the application layer releases them after persistence (outbox).
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    final protected function recordEvent(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Returns and clears the pending domain events.
     *
     * @return list<DomainEvent>
     */
    final public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
