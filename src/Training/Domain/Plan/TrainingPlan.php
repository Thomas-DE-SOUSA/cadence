<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Plan;

/**
 * A ready-made, expert-designed training plan: a periodised roadmap of phases
 * from the current fitness toward a target race.
 */
final readonly class TrainingPlan
{
    /**
     * @param list<PlanPhase> $phases
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $summary,
        public string $goal,
        public string $targetRaceName,
        public int $daysPerWeek,
        public array $phases,
    ) {
    }

    public function phase(int $index): ?PlanPhase
    {
        return $this->phases[$index] ?? null;
    }

    public function phaseCount(): int
    {
        return count($this->phases);
    }

    public function totalWeeks(): int
    {
        return array_sum(array_map(static fn (PlanPhase $p): int => $p->weeks, $this->phases));
    }
}
