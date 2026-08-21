<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Port;

use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\ValueObject\CoachContext;
use Cadence\Coaching\Domain\ValueObject\CoachReply;

interface CoachStreamer
{
    /**
     * Streams the coach's answer, invoking $onText with each text delta, and
     * returns the complete reply (text + optional proposal) once finished.
     *
     * @param list<Message> $history
     * @param callable(string):void $onText
     */
    public function stream(CoachContext $context, array $history, callable $onText): CoachReply;
}
