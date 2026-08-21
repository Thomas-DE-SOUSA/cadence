<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Plan;

use Cadence\Training\Domain\ValueObject\PlannedCycle;
use Cadence\Training\Domain\ValueObject\PlannedSessionData;

/**
 * Turns an expert plan phase into a concrete, day-by-day planned cycle by
 * repeating the weekly pattern across the phase's weeks.
 */
final class PhaseMaterializer
{
    public static function toPlannedCycle(PlanPhase $phase): PlannedCycle
    {
        $sessions = [];

        for ($week = 0; $week < $phase->weeks; $week++) {
            foreach ($phase->pattern as $template) {
                $sessions[] = new PlannedSessionData(
                    $week * 7 + $template->dayInWeek,
                    $template->type->value,
                    $template->title,
                    $template->description,
                    $template->targetDistanceMeters,
                    $template->targetDurationSeconds,
                    $template->targetPaceSecondsPerKm,
                );
            }
        }

        return new PlannedCycle($phase->name, $phase->focus, $sessions);
    }
}
