<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Port;

use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\ValueObject\CoachContext;
use Cadence\Coaching\Domain\ValueObject\CoachReply;

interface CoachChat
{
    /**
     * @param list<Message> $history prior turns, oldest first
     */
    public function reply(CoachContext $context, array $history): CoachReply;
}
