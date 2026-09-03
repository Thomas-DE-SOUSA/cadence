<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Port;

use Cadence\Coaching\Domain\ValueObject\ProgramGoal;
use Cadence\Shared\Domain\TenantId;

/**
 * The tenant's current running goal, independent of any single training day —
 * a dedicated seam so the weekly review can frame itself without depending on
 * the per-day {@see ProgramContextProvider}.
 */
interface AthleteGoalProvider
{
    public function currentGoal(TenantId $tenant): ?ProgramGoal;
}
