<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/**
 * Everything the weekly coach reasons from: the athlete's goal, the running
 * side of the week (already analysed by AthleteBrief — volume, 80/20, load,
 * readiness, verdict) and the strength side ({@see StrengthWeekSummary}), so it
 * can judge the week as a whole and balance the two disciplines.
 */
final readonly class WeeklyReviewContext
{
    public function __construct(
        public string $weekStart,
        public string $weekEnd,
        public string $goal,
        public string $targetRaceName,
        public ?string $targetRaceDate,
        public ?FitnessSnapshot $fitness,
        public string $runningAnalysis,
        public string $recentRunsSummary,
        public StrengthWeekSummary $strength,
    ) {
    }
}
