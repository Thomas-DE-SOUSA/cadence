<?php

declare(strict_types=1);

namespace Cadence\Shared\Application;

use Cadence\Shared\Domain\TenantId;

/**
 * Server-authoritative source of the acting tenant. Tenant identity NEVER comes
 * from the request payload — an adapter resolves it from the auth guard (or a
 * fixed dev tenant until auth lands).
 */
interface TenantContext
{
    public function current(): TenantId;
}
