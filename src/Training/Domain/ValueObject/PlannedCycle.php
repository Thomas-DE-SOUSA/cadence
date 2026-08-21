<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\ValueObject;

/** The planner's output: a named cycle with its planned sessions. */
final class PlannedCycle
{
    /**
     * @param list<PlannedSessionData> $sessions
     */
    public function __construct(
        public readonly string $name,
        public readonly string $focus,
        public readonly array $sessions,
    ) {
    }
}
