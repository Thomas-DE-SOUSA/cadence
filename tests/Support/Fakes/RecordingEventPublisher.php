<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Cadence\Shared\Application\EventPublisher;
use Cadence\Shared\Domain\DomainEvent;

final class RecordingEventPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->published[] = $event;
        }
    }
}
