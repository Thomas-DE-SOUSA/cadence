<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Plan;

/**
 * A phase (mesocycle) of an expert training plan: a focus held for a number of
 * weeks, each week following the same session pattern.
 */
final readonly class PlanPhase
{
    /**
     * @param list<PlanSessionTemplate> $pattern
     */
    public function __construct(
        public string $name,
        public string $focus,
        public int $weeks,
        public array $pattern,
    ) {
    }
}
