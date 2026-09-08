<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Coaching\Application\WeeklyReview\WeekAnchor;
use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\Port\AthleteGoalProvider;
use Cadence\Coaching\Domain\Port\StrengthContextProvider;
use Cadence\Coaching\Domain\Port\WeeklyReviewRepository;
use Cadence\Coaching\Domain\ValueObject\StrengthWeekSummary;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Inertia\Inertia;
use Inertia\Response;

/** Renders the weekly cross-modal review page with this week's numbers and the saved thread. */
final class ShowWeeklyReviewController
{
    public function __construct(
        private readonly StrengthContextProvider $strength,
        private readonly WeeklyReviewRepository $reviews,
        private readonly AthleteGoalProvider $goals,
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(): Response
    {
        $tenant = $this->tenantContext->current();
        $today = $this->clock->now()->format('Y-m-d');
        $weekStart = WeekAnchor::monday(null, $this->clock);
        $weekEnd = (new \DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');

        $review = $this->reviews->forWeek($weekStart, $tenant);
        $goal = $this->goals->currentGoal($tenant);

        return Inertia::render('MuscuBilan', [
            'today' => $today,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'goal' => $goal === null ? null : $goal->goal,
            'strength' => $this->strengthView($this->strength->weekSummary($tenant, $today)),
            'thread' => $review === null ? [] : array_map(
                static fn (Message $m): array => ['role' => $m->role->value, 'text' => $m->text],
                $review->messages(),
            ),
        ]);
    }

    /** @return array<string, mixed> */
    private function strengthView(StrengthWeekSummary $s): array
    {
        return [
            'hasData' => $s->hasData(),
            'sessions' => $s->sessions,
            'workingSets' => $s->workingSets,
            'tonnageKg' => $s->tonnageKg,
            'legSessions' => $s->legSessions,
            'prevSessions' => $s->prevSessions,
            'prevTonnageKg' => $s->prevTonnageKg,
            'daysSinceLast' => $s->daysSinceLast,
            'muscleBalance' => $s->muscleBalance,
            'weightAvgKg' => $s->weightAvgKg,
            'weightPrevAvgKg' => $s->weightPrevAvgKg,
        ];
    }
}
