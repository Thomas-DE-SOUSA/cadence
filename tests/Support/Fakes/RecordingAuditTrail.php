<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Domain\TenantId;
use DateTimeImmutable;

final class RecordingAuditTrail implements AuditTrail
{
    /** @var list<array{action:string,tenant:string,aggregate_id:string,snapshot:array<string,mixed>}> */
    public array $entries = [];

    public function record(
        string $action,
        TenantId $tenant,
        string $aggregateId,
        array $snapshot,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->entries[] = [
            'action' => $action,
            'tenant' => $tenant->value,
            'aggregate_id' => $aggregateId,
            'snapshot' => $snapshot,
        ];
    }
}
