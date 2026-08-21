<?php

declare(strict_types=1);

namespace Cadence\Coaching\Application\SendCoachMessage;

use Cadence\Coaching\Application\CoachTurnService;
use Cadence\Coaching\Domain\Port\CoachChat;
use Cadence\Coaching\Domain\ValueObject\ConversationId;
use Cadence\Shared\Application\ExecutionContext;

/**
 * Blocking coach turn: record the athlete's message, ask the coach, store the
 * reply. The streaming controller uses CoachTurnService directly.
 */
final readonly class SendCoachMessageUseCase
{
    public function __construct(
        private CoachTurnService $turns,
        private CoachChat $coach,
    ) {
    }

    public function execute(SendCoachMessageInput $input, ExecutionContext $context): ConversationId
    {
        $turn = $this->turns->start($input->programId, $input->cycleId, $input->sessionDate, $input->text, $context);

        $reply = $this->coach->reply($turn->context, $turn->history);

        $this->turns->finish($turn->conversationId, $reply->text, $reply->proposal, $context);

        return $turn->conversationId;
    }
}
