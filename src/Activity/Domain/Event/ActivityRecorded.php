<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\Event;

use Cadence\Shared\Domain\DomainEvent;

final class ActivityRecorded extends DomainEvent
{
    public const NAME = 'activity.recorded';

    public function __construct(
        string $aggregateId,
        \DateTimeImmutable $occurredAt,
        public readonly string $tenantId,
        public readonly int $distanceMeters,
        public readonly int $movingSeconds,
        public readonly float $averagePaceSecondsPerKm,
        public readonly string $source,
    ) {
        parent::__construct($aggregateId, $occurredAt);
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function payload(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'distance_meters' => $this->distanceMeters,
            'moving_seconds' => $this->movingSeconds,
            'average_pace_seconds_per_km' => $this->averagePaceSecondsPerKm,
            'source' => $this->source,
        ];
    }
}
