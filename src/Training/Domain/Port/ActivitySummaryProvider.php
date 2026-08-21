<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Port;

use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Domain\ValueObject\ActivitySummary;

/**
 * Read-only view of the Activity context, used to evaluate program objectives.
 * The Training context never touches Activity internals directly.
 */
interface ActivitySummaryProvider
{
    /**
     * @param list<string> $activityIds
     *
     * @return list<ActivitySummary>
     */
    public function summariesFor(TenantId $tenant, array $activityIds): array;
}
