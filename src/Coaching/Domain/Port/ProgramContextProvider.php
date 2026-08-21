<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Port;

use Cadence\Coaching\Domain\ValueObject\ProgramDay;
use Cadence\Shared\Domain\TenantId;

interface ProgramContextProvider
{
    /** The program goal context and the planned day, or null if not found. */
    public function forDay(string $programId, string $cycleId, string $sessionDate, TenantId $tenant): ?ProgramDay;
}
