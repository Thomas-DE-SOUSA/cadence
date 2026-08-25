<?php

declare(strict_types=1);

namespace Cadence\Shared\Infrastructure;

use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Domain\TenantId;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Resolves the current tenant from the authenticated user. Each account owns a
 * private tenant (users.tenant_id), so every tenant-scoped query is naturally
 * isolated per account. Falls back to a server-controlled tenant for
 * unauthenticated contexts (console commands, queue workers, health checks) —
 * the client can never influence it.
 */
final class AuthTenantContext implements TenantContext
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly string $fallbackTenantId,
    ) {
    }

    public function current(): TenantId
    {
        $user = $this->auth->guard()->user();
        $tenantId = $user !== null && isset($user->tenant_id) && $user->tenant_id !== ''
            ? $user->tenant_id
            : $this->fallbackTenantId;

        return TenantId::fromString($tenantId);
    }
}
