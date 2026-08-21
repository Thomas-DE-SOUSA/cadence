<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Port;

use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Domain\Model\Cycle;
use Cadence\Training\Domain\ValueObject\CycleId;

interface CycleRepository
{
    /** Insert a new cycle or update an existing one. */
    public function save(Cycle $cycle): void;

    /** @return list<Cycle> ordered by phase index ascending. */
    public function forProgram(string $programId, TenantId $tenant): array;

    public function latestForProgram(string $programId, TenantId $tenant): ?Cycle;

    public function ofId(CycleId $id, TenantId $tenant): ?Cycle;
}
