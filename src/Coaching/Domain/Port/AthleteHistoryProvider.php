<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Port;

use Cadence\Coaching\Domain\ValueObject\PerformancePoint;
use Cadence\Shared\Domain\TenantId;

interface AthleteHistoryProvider
{
    /** @return list<PerformancePoint> the tenant's logged runs, most recent first. */
    public function recentFor(TenantId $tenant): array;

    /** A coaching read of recent training (volume, easy-pace discipline, form, verdict). */
    public function analysisFor(TenantId $tenant): string;
}
