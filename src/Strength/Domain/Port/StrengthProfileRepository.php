<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Port;

use Cadence\Strength\Domain\Model\StrengthProfile;
use Cadence\Shared\Domain\TenantId;

interface StrengthProfileRepository
{
    public function save(StrengthProfile $profile): void;

    public function forTenant(TenantId $tenant): ?StrengthProfile;
}
