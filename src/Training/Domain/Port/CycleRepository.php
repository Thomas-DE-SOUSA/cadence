<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Port;

use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Domain\Model\Cycle;

interface CycleRepository
{
    public function save(Cycle $cycle): void;

    /** @return list<Cycle> ordered by start date ascending. */
    public function forProgram(string $programId, TenantId $tenant): array;

    public function latestForProgram(string $programId, TenantId $tenant): ?Cycle;
}
