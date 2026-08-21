<?php

declare(strict_types=1);

namespace Cadence\Coaching\Application\SendCoachMessage;

final readonly class SendCoachMessageInput
{
    public function __construct(
        public string $programId,
        public string $cycleId,
        public string $sessionDate,
        public string $text,
    ) {
    }
}
