<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Port;

use Cadence\Training\Domain\ValueObject\PlannedCycle;
use Cadence\Training\Domain\ValueObject\PlannerContext;

/** Designs a training cycle from the program context and the athlete's feedback (implemented by an LLM adapter). */
interface CyclePlanner
{
    public function plan(PlannerContext $context): PlannedCycle;
}
