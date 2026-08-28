<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\LogStrengthSession;

final readonly class LogStrengthSessionInput
{
    /**
     * @param list<array<string, mixed>> $exercises each in the PerformedExercise array shape
     */
    public function __construct(
        public ?string $id,
        public string $date,
        public string $title,
        public string $note,
        public ?int $durationSeconds,
        public array $exercises,
    ) {
    }
}
