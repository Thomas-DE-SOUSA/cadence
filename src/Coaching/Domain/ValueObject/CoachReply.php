<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/** The coach's answer: prose, plus an optional structured day change to confirm. */
final readonly class CoachReply
{
    public function __construct(
        public string $text,
        public ?SessionProposal $proposal,
    ) {
    }
}
