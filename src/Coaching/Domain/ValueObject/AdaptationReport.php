<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/**
 * A weekly training recommendation derived from compliance, load and the
 * intensity balance. `consigne` is a ready-to-use instruction for the cycle
 * planner so the next cycle reflects the analysis.
 */
final readonly class AdaptationReport
{
    /**
     * @param 'progress'|'hold'|'rebalance'|'deload' $verdict
     * @param list<string> $reasons
     * @param list<string> $suggestions
     */
    public function __construct(
        public string $verdict,
        public string $headline,
        public array $reasons,
        public array $suggestions,
        public string $consigne,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'verdict' => $this->verdict,
            'headline' => $this->headline,
            'reasons' => $this->reasons,
            'suggestions' => $this->suggestions,
            'consigne' => $this->consigne,
        ];
    }
}
