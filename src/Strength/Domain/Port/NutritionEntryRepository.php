<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Port;

use Cadence\Shared\Domain\TenantId;
use Cadence\Strength\Domain\ValueObject\NutritionEntry;

/** Persists logged food items (many per day, per tenant). */
interface NutritionEntryRepository
{
    public function save(TenantId $tenant, NutritionEntry $entry): void;

    /**
     * All entries logged on {@see $date} (Y-m-d), oldest first.
     *
     * @return list<NutritionEntry>
     */
    public function forDate(TenantId $tenant, string $date): array;

    public function delete(TenantId $tenant, string $id): void;
}
