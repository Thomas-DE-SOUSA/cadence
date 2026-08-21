<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\CreateProgram;

final readonly class ObjectiveInput
{
    public function __construct(
        public string $type,
        public string $label,
        public ?int $targetDistanceMeters = null,
        public ?int $targetSeconds = null,
        public ?float $targetPaceSecondsPerKm = null,
        public ?int $targetCount = null,
    ) {
    }
}
