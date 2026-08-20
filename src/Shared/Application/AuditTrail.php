<?php

declare(strict_types=1);

namespace Cadence\Shared\Application;

use Cadence\Shared\Domain\TenantId;
use DateTimeImmutable;

/** Records an immutable audit entry for every state mutation. */
interface AuditTrail
{
    /**
     * @param array<string, mixed> $snapshot
     */
    public function record(
        string $action,
        TenantId $tenant,
        string $aggregateId,
        array $snapshot,
        DateTimeImmutable $occurredAt,
    ): void;
}
