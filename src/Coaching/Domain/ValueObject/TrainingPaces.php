<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/** Personalised training paces (seconds per km) per Daniels intensity zone. */
final readonly class TrainingPaces
{
    public function __construct(
        public int $easy,
        public int $marathon,
        public int $threshold,
        public int $interval,
        public int $repetition,
    ) {
    }

    /** @return array{easy:int,marathon:int,threshold:int,interval:int,repetition:int} */
    public function toArray(): array
    {
        return [
            'easy' => $this->easy,
            'marathon' => $this->marathon,
            'threshold' => $this->threshold,
            'interval' => $this->interval,
            'repetition' => $this->repetition,
        ];
    }
}
