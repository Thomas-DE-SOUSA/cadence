<?php

declare(strict_types=1);

namespace Cadence\Shared\Application;

use Cadence\Shared\Domain\TenantId;

/**
 * The authenticated context a use case runs in. Carries the acting tenant today;
 * the acting user id is added when authentication lands.
 */
final readonly class ExecutionContext
{
    public function __construct(public TenantId $tenant)
    {
    }
}
