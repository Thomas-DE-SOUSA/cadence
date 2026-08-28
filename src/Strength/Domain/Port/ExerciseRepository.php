<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Port;

use Cadence\Strength\Domain\Model\Exercise;
use Cadence\Shared\Domain\TenantId;

interface ExerciseRepository
{
    public function save(Exercise $exercise): void;

    public function ofId(string $id, TenantId $tenant): ?Exercise;

    /**
     * The catalogue visible to a tenant: the global library plus their own
     * custom exercises.
     *
     * @return list<Exercise>
     */
    public function forTenant(TenantId $tenant): array;
}
