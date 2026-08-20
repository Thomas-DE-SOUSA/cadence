<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Cadence\Shared\Application\EventPublisher;
use RuntimeException;

/** A publisher that always fails — used to prove the aggregate is saved regardless. */
final class ThrowingEventPublisher implements EventPublisher
{
    public function publish(array $events): void
    {
        throw new RuntimeException('publisher is down');
    }
}
