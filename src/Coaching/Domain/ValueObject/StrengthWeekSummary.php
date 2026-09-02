<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/**
 * The strength (muscu) side of one Mon–Sun week, shaped for the weekly coach:
 * how much was lifted, how often, which muscles, the leg load (which competes
 * with running recovery), the trend vs the previous week, and the body-weight
 * drift. Pure data — the prompt builder formats it.
 */
final readonly class StrengthWeekSummary
{
    /**
     * @param list<array{muscle:string,label:string,sets:int}> $muscleBalance working sets per muscle, most first
     */
    public function __construct(
        public string $weekStart,
        public string $weekEnd,
        public int $sessions,
        public int $workingSets,
        public float $tonnageKg,
        public int $legSessions,
        public int $prevSessions,
        public float $prevTonnageKg,
        public ?int $daysSinceLast,
        public array $muscleBalance,
        public ?float $weightAvgKg,
        public ?float $weightPrevAvgKg,
    ) {
    }

    public function hasData(): bool
    {
        return $this->sessions > 0 || $this->prevSessions > 0;
    }
}
