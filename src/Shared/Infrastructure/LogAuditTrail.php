<?php

declare(strict_types=1);

namespace Cadence\Shared\Infrastructure;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Domain\TenantId;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

/**
 * Minimal audit sink writing to the application log. A persisted audit table
 * can replace this later without touching the application layer.
 */
final class LogAuditTrail implements AuditTrail
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function record(
        string $action,
        TenantId $tenant,
        string $aggregateId,
        array $snapshot,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->logger->info('audit', [
            'action' => $action,
            'tenant_id' => $tenant->value,
            'aggregate_id' => $aggregateId,
            'occurred_at' => $occurredAt->format(DATE_ATOM),
            'snapshot' => $snapshot,
        ]);
    }
}
