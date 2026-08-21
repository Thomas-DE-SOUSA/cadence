<?php

declare(strict_types=1);

namespace Cadence\Coaching\Application;

use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\ValueObject\CoachContext;
use Cadence\Coaching\Domain\ValueObject\ConversationId;

/** A prepared coach turn: the conversation, the context and the history so far. */
final readonly class CoachTurn
{
    /**
     * @param list<Message> $history
     */
    public function __construct(
        public ConversationId $conversationId,
        public CoachContext $context,
        public array $history,
    ) {
    }
}
