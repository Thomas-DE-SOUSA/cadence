<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Port;

use Cadence\Shared\Domain\TenantId;
use Cadence\Strength\Domain\ValueObject\WeightEntry;

/** Persists body-weight readings (one per moment per day, per tenant). */
interface WeightEntryRepository
{
    public function save(TenantId $tenant, WeightEntry $entry): void;

    /**
     * Entries on or after {@see $since} (Y-m-d), most recent first.
     *
     * @return list<WeightEntry>
     */
    public function since(TenantId $tenant, string $since): array;

    public function latestFor(TenantId $tenant): ?WeightEntry;
}
