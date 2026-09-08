<?php

declare(strict_types=1);

namespace Cadence\Coaching\Application\WeeklyReview;

use Cadence\Coaching\Application\FitnessAssessmentService;
use Cadence\Coaching\Domain\Model\WeeklyReview;
use Cadence\Coaching\Domain\Port\AthleteGoalProvider;
use Cadence\Coaching\Domain\Port\AthleteHistoryProvider;
use Cadence\Coaching\Domain\Port\StrengthContextProvider;
use Cadence\Coaching\Domain\Port\WeeklyReviewRepository;
use Cadence\Coaching\Domain\ValueObject\PerformancePoint;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewContext;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewId;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Identifier\IdGenerator;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Weekly cross-modal review lifecycle (mirrors {@see \Cadence\Coaching\Application\CoachTurnService}):
 * start() records the athlete's message and assembles the running + strength
 * context for the week; finish() records the coach's verdict.
 */
final readonly class WeeklyReviewService
{
    public function __construct(
        private WeeklyReviewRepository $reviews,
        private StrengthContextProvider $strength,
        private AthleteHistoryProvider $history,
        private FitnessAssessmentService $fitness,
        private AthleteGoalProvider $goals,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function start(string $weekStart, string $text, ExecutionContext $context): WeeklyTurn
    {
        $tenant = $context->tenant;

        // Assemble the (fallible) cross-modal context BEFORE persisting anything,
        // so a provider failure never leaves a dangling athlete-only turn.
        $reviewContext = $this->assemble($weekStart, $context);

        $review = $this->reviews->forWeek($weekStart, $tenant)
            ?? WeeklyReview::start(WeeklyReviewId::generate($this->ids), $tenant, $weekStart);

        $review->pruneUnansweredAthleteTail(); // drop a prior aborted/failed athlete turn on retry
        $review->addAthleteMessage($this->ids->generate(), $text, $this->now());
        $this->reviews->save($review);

        return new WeeklyTurn($review->id(), $reviewContext, $review->messages());
    }

    public function finish(WeeklyReviewId $reviewId, string $text, ExecutionContext $context): void
    {
        $review = $this->reviews->ofId($reviewId, $context->tenant);
        if ($review === null) {
            return;
        }

        $review->addCoachMessage($this->ids->generate(), $text, $this->now());
        $this->reviews->save($review);
    }

    private function assemble(string $weekStart, ExecutionContext $context): WeeklyReviewContext
    {
        $tenant = $context->tenant;
        $weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
        $today = $this->clock->now()->format('Y-m-d');
        // Anchor the strength summary inside the reviewed week; never past today,
        // so an unfinished current week doesn't count sessions that haven't happened.
        $anchor = min($weekEnd, $today);

        $goal = $this->goals->currentGoal($tenant);

        return new WeeklyReviewContext(
            $weekStart,
            $weekEnd,
            $goal === null ? '' : $goal->goal,
            $goal === null ? '' : $goal->targetRaceName,
            $goal === null ? null : $goal->targetRaceDate,
            $this->fitness->forTenant($tenant),
            $this->history->analysisFor($tenant),
            $this->summariseRecent($this->history->recentFor($tenant)),
            $this->strength->weekSummary($tenant, $anchor),
        );
    }

    private function now(): string
    {
        return $this->clock->now()->format(DateTimeInterface::ATOM);
    }

    /** @param list<PerformancePoint> $history */
    private function summariseRecent(array $history): string
    {
        $lines = [];
        foreach (array_slice($history, 0, 8) as $point) {
            if ($point->distanceMeters <= 0 || $point->movingSeconds <= 0) {
                continue;
            }
            $pace = (int) round($point->movingSeconds / ($point->distanceMeters / 1000));
            $lines[] = sprintf('%s: %.2f km, allure %d s/km', substr($point->occurredAt, 0, 10), $point->distanceMeters / 1000, $pace);
        }

        return $lines === [] ? 'Aucune sortie récente enregistrée.' : implode(' ; ', $lines);
    }
}
