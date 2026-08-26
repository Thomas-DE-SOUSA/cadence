<?php

declare(strict_types=1);

namespace Cadence\Coaching\Application;

use Cadence\Coaching\Domain\Exception\DayContextMissing;
use Cadence\Coaching\Domain\Model\Conversation;
use Cadence\Coaching\Domain\Port\AthleteHistoryProvider;
use Cadence\Coaching\Domain\Port\ConversationRepository;
use Cadence\Coaching\Domain\Port\ProgramContextProvider;
use Cadence\Coaching\Domain\ValueObject\CoachContext;
use Cadence\Coaching\Domain\ValueObject\ConversationId;
use Cadence\Coaching\Domain\ValueObject\PerformancePoint;
use Cadence\Coaching\Domain\ValueObject\SessionProposal;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Identifier\IdGenerator;
use DateTimeInterface;

/**
 * Shared coach turn lifecycle used by both the blocking and streaming paths:
 * start() records the athlete's message and assembles the context; finish()
 * records the coach's reply.
 */
final readonly class CoachTurnService
{
    public function __construct(
        private ConversationRepository $conversations,
        private ProgramContextProvider $programs,
        private FitnessAssessmentService $fitness,
        private AthleteHistoryProvider $history,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function start(string $programId, string $cycleId, string $sessionDate, string $text, ExecutionContext $context): CoachTurn
    {
        $tenant = $context->tenant;

        $programDay = $this->programs->forDay($programId, $cycleId, $sessionDate, $tenant);
        if ($programDay === null) {
            throw DayContextMissing::forDay($programId, $sessionDate);
        }

        $conversation = $this->conversations->forDay($programId, $sessionDate, $tenant)
            ?? Conversation::start(ConversationId::generate($this->ids), $tenant, $programId, $cycleId, $sessionDate);

        $conversation->addAthleteMessage($this->ids->generate(), $text, $this->now());
        $this->conversations->save($conversation);

        $coachContext = new CoachContext(
            $programDay->goal,
            $programDay->targetRaceName,
            $programDay->targetRaceDate,
            $this->fitness->forTenant($tenant),
            $programDay->targetVdot,
            $this->summariseRecent($this->history->recentFor($tenant)),
            $programDay->day,
            $this->history->analysisFor($tenant),
        );

        return new CoachTurn($conversation->id(), $coachContext, $conversation->messages());
    }

    public function finish(ConversationId $conversationId, string $text, ?SessionProposal $proposal, ExecutionContext $context): void
    {
        $conversation = $this->conversations->ofId($conversationId, $context->tenant);
        if ($conversation === null) {
            return;
        }

        $conversation->addCoachMessage($this->ids->generate(), $text, $proposal, $this->now());
        $this->conversations->save($conversation);
    }

    private function now(): string
    {
        return $this->clock->now()->format(DateTimeInterface::ATOM);
    }

    /** @param list<PerformancePoint> $history */
    private function summariseRecent(array $history): string
    {
        $lines = [];
        foreach (array_slice($history, 0, 10) as $point) {
            if ($point->distanceMeters <= 0 || $point->movingSeconds <= 0) {
                continue;
            }
            $pace = (int) round($point->movingSeconds / ($point->distanceMeters / 1000));
            $lines[] = sprintf('%s: %.2f km, allure %d s/km', substr($point->occurredAt, 0, 10), $point->distanceMeters / 1000, $pace);
        }

        return $lines === [] ? 'Aucune sortie récente enregistrée.' : implode(' ; ', $lines);
    }
}
