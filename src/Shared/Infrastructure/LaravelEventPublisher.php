<?php

declare(strict_types=1);

namespace Cadence\Shared\Infrastructure;

use Cadence\Shared\Application\EventPublisher;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Best-effort publisher: dispatches each domain event on the framework bus.
 * Durability is guaranteed by the outbox row written in the same transaction;
 * a failure here is logged and swallowed, never rethrown.
 */
final class LaravelEventPublisher implements EventPublisher
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(array $events): void
    {
        foreach ($events as $event) {
            try {
                $this->dispatcher->dispatch($event->name(), $event);
            } catch (Throwable $e) {
                $this->logger->error('Failed to publish domain event', [
                    'event' => $event->name(),
                    'aggregate_id' => $event->aggregateId,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
