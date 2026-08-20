<?php

declare(strict_types=1);

use Cadence\Activity\Domain\Event\ActivityRecorded;
use Cadence\Shared\Infrastructure\LaravelEventPublisher;
use Illuminate\Events\Dispatcher;
use Psr\Log\NullLogger;

describe('Feature: Best-effort event publishing', function (): void {
    it('never lets a bus failure bubble out of the publisher', function (): void {
        $failingBus = new class extends Dispatcher
        {
            public function dispatch($event, $payload = [], $halt = false): ?array
            {
                throw new RuntimeException('bus down');
            }
        };

        $publisher = new LaravelEventPublisher($failingBus, new NullLogger());

        $event = new ActivityRecorded(
            'activity-1',
            new DateTimeImmutable('2026-08-19T18:00:00+00:00'),
            'tenant-thomas',
            10010,
            2555,
            255.24,
            'MANUAL',
        );

        $publisher->publish([$event]);

        // Reaching this line means the failure was swallowed as designed.
        expect(true)->toBeTrue();
    });
});
