<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\Port;

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
}
