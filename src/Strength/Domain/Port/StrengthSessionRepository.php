<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Port;

use Cadence\Strength\Domain\Model\StrengthSession;
use Cadence\Shared\Domain\TenantId;

interface StrengthSessionRepository
{
    public function save(StrengthSession $session): void;

    public function ofId(string $id, TenantId $tenant): ?StrengthSession;

    /**
     * A tenant's sessions, most recent first.
     *
     * @return list<StrengthSession>
     */
    public function forTenant(TenantId $tenant, int $limit = 50): array;
}
