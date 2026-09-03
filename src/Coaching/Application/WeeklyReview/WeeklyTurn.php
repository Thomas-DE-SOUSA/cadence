<?php

declare(strict_types=1);

namespace Cadence\Coaching\Application\WeeklyReview;

use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewContext;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewId;

/** A prepared weekly-review turn: the thread id, the assembled context, the history so far. */
final readonly class WeeklyTurn
{
    /**
     * @param list<Message> $history
     */
    public function __construct(
        public WeeklyReviewId $reviewId,
        public WeeklyReviewContext $context,
        public array $history,
    ) {
    }
}
