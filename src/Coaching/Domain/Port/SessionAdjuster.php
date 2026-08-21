<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Port;

use Cadence\Coaching\Domain\ValueObject\SessionProposal;
use Cadence\Shared\Domain\TenantId;

interface SessionAdjuster
{
    /** Apply an accepted proposal to the plan (delegates to Training). */
    public function apply(string $programId, string $cycleId, SessionProposal $proposal, TenantId $tenant): void;
}
