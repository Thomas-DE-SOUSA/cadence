<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Port;

use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\ValueObject\CoachReply;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewContext;

/** Streams the weekly cross-modal verdict (running + strength), forwarding each text delta. */
interface WeeklyCoachStreamer
{
    /**
     * @param list<Message>         $history prior turns of this week's review, oldest first
     * @param callable(string):void $onText  called with each streamed text delta
     */
    public function stream(WeeklyReviewContext $context, array $history, callable $onText): CoachReply;
}
