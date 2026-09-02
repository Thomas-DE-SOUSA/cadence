<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Port;

use Cadence\Coaching\Domain\ValueObject\StrengthWeekSummary;
use Cadence\Shared\Domain\TenantId;

/**
 * Supplies the strength (muscu) side of the weekly coach. The seam that keeps
 * Coaching from reaching into the Strength context directly — an adapter
 * translates strength sessions/weight into a {@see StrengthWeekSummary}.
 */
interface StrengthContextProvider
{
    public function weekSummary(TenantId $tenant, string $today): StrengthWeekSummary;
}
