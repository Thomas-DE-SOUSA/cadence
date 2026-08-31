<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Port;

use Cadence\Strength\Domain\Model\MuscuProfile;
use Cadence\Shared\Domain\TenantId;

interface MuscuProfileRepository
{
    public function save(MuscuProfile $profile): void;

    public function forTenant(TenantId $tenant): ?MuscuProfile;
}
