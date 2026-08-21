<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Exception;

use Cadence\Shared\Domain\DomainException;

final class ConversationNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(CoachingErrorCode::CONVERSATION_NOT_FOUND, "Conversation {$id} was not found.");
    }
}
