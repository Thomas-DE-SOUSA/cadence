<?php

declare(strict_types=1);

namespace Cadence\Coaching\Application\UseCase\SubmitWellnessCheckIn;

final readonly class SubmitWellnessCheckInInput
{
    public function __construct(
        public int $sleep,
        public int $energy,
        public int $legs,
        public int $motivation,
        public int $painLevel = 0,
        public string $painLocation = '',
        public string $note = '',
    ) {
    }
}
