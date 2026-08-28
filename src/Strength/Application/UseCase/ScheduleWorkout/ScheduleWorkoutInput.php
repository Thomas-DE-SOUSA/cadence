<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\ScheduleWorkout;

final readonly class ScheduleWorkoutInput
{
    public function __construct(
        public string $templateId,
        public string $date,
    ) {
    }
}
