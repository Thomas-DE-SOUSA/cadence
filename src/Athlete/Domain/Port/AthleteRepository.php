<?php

declare(strict_types=1);

namespace Cadence\Athlete\Domain\Port;

use Cadence\Athlete\Domain\Model\Athlete;
use Cadence\Shared\Domain\DomainEvent;
use Cadence\Shared\Domain\TenantId;

interface AthleteRepository
{
    /** @param list<DomainEvent> $events */
    public function save(Athlete $athlete, array $events): void;

    /** The single profile for a tenant, or null if none exists yet. */
    public function ofTenant(TenantId $tenant): ?Athlete;
}
