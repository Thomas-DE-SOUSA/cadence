<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Port;

use Cadence\Strength\Domain\Model\WorkoutTemplate;
use Cadence\Shared\Domain\TenantId;

interface WorkoutTemplateRepository
{
    public function save(WorkoutTemplate $template): void;

    public function ofId(string $id, TenantId $tenant): ?WorkoutTemplate;

    public function delete(string $id, TenantId $tenant): void;

    /**
     * A tenant's templates, by name.
     *
     * @return list<WorkoutTemplate>
     */
    public function forTenant(TenantId $tenant): array;
}
