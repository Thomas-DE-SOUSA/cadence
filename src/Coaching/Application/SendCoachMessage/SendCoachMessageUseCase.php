<?php

declare(strict_types=1);

namespace Cadence\Coaching\Application\SendCoachMessage;

use Cadence\Coaching\Application\FitnessAssessmentService;
use Cadence\Coaching\Domain\Exception\DayContextMissing;
use Cadence\Coaching\Domain\Model\Conversation;
use Cadence\Coaching\Domain\Port\AthleteHistoryProvider;
use Cadence\Coaching\Domain\Port\CoachChat;
use Cadence\Coaching\Domain\Port\ConversationRepository;
use Cadence\Coaching\Domain\Port\ProgramContextProvider;
use Cadence\Coaching\Domain\ValueObject\CoachContext;
use Cadence\Coaching\Domain\ValueObject\ConversationId;
use Cadence\Coaching\Domain\ValueObject\PerformancePoint;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Identifier\IdGenerator;
use DateTimeInterface;

/**
 * Appends the athlete's message to the day's conversation, asks the coach
 * (grounded in the athlete's profile and the doctrine) and stores the reply.
 */
final readonly class SendCoachMessageUseCase
{
    public function __construct(
        private ConversationRepository $conversations,
        private ProgramContextProvider $programs,
        private FitnessAssessmentService $fitness,
        private AthleteHistoryProvider $history,
        private CoachChat $coach,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function execute(SendCoachMessageInput $input, ExecutionContext $context): ConversationId
    {
        $tenant = $context->tenant;

        $programDay = $this->programs->forDay($input->programId, $input->cycleId, $input->sessionDate, $tenant);
        if ($programDay === null) {
            throw DayContextMissing::forDay($input->programId, $input->sessionDate);
        }

        $conversation = $this->conversations->forDay($input->programId, $input->sessionDate, $tenant)
            ?? Conversation::start(
                ConversationId::generate($this->ids),
                $tenant,
                $input->programId,
                $input->cycleId,
                $input->sessionDate,
            );

        $conversation->addAthleteMessage($this->ids->generate(), $input->text, $this->now());

        $reply = $this->coach->reply(
            new CoachContext(
                $programDay->goal,
                $programDay->targetRaceName,
                $programDay->targetRaceDate,
                $this->fitness->forTenant($tenant),
                $programDay->targetVdot,
                $this->summariseRecent($this->history->recentFor($tenant)),
                $programDay->day,
            ),
            $conversation->messages(),
        );

        $conversation->addCoachMessage($this->ids->generate(), $reply->text, $reply->proposal, $this->now());
        $this->conversations->save($conversation);

        return $conversation->id();
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
