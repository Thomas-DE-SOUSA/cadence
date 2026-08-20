<?php

declare(strict_types=1);

namespace Cadence\Shared\Infrastructure;

use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Domain\TenantId;

/**
 * Interim tenant resolver used until authentication lands. Resolves a single,
 * server-controlled dev tenant — the client can never influence it. Swap this
 * binding for an auth-guard-backed adapter later; nothing else changes.
 */
final class FixedTenantContext implements TenantContext
{
    public function __construct(private readonly string $tenantId)
    {
    }

    public function current(): TenantId
    {
        return TenantId::fromString($this->tenantId);
    }
}
