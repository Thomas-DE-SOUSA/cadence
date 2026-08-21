<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\Port;

use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Activity\Domain\Model\Activity;
use Cadence\Activity\Domain\ValueObject\ActivityId;
use Cadence\Shared\Domain\DomainEvent;
use Cadence\Shared\Domain\TenantId;

interface ActivityRepository
{
    /**
     * Persists the aggregate and its released events atomically (outbox).
     *
     * @param list<DomainEvent> $events
     */
    public function save(Activity $activity, array $events): void;

    public function ofId(ActivityId $id, TenantId $tenant): ?Activity;

    /** Soft-deletes the activity (tenant-scoped). */
    public function delete(ActivityId $id, TenantId $tenant): void;

    /** True when an activity from this provider is already imported (dedup guard). */
    public function existsForExternalId(TenantId $tenant, ActivitySource $source, string $externalId): bool;

    /**
     * True when a "same run" already exists: same day, and distance/moving time
     * within the given inclusive bounds (any source).
     */
    public function hasActivityOn(
        TenantId $tenant,
        string $day,
        int $minDistanceMeters,
        int $maxDistanceMeters,
        int $minMovingSeconds,
        int $maxMovingSeconds,
    ): bool;
}
