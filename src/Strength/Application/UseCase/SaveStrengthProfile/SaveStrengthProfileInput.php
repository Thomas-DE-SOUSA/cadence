<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\SaveStrengthProfile;

final readonly class SaveStrengthProfileInput
{
    /**
     * @param list<string> $priorities
     * @param list<string> $limitations
     */
    public function __construct(
        public string $goal,
        public string $level,
        public ?float $bodyweightKg,
        public int $weeklyFrequency,
        public string $split,
        public string $equipment,
        public array $priorities,
        public array $limitations,
        public string $note = '',
    ) {
    }
}
