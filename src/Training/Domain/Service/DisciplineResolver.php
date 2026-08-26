<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Service;

use Cadence\Training\Domain\Enum\Discipline;

/** Derives a program's {@see Discipline} from its objective (race, goal, distance). */
final class DisciplineResolver
{
    /** @param array<string, mixed> $snapshot a TrainingProgram snapshot */
    public static function forSnapshot(array $snapshot): Discipline
    {
        // The RACE distance drives the discipline — not volume/session-count goals
        // (a "150 km on the block" target must not read as an ultra).
        $distance = 0;
        $objectives = $snapshot['objectives'] ?? [];
        if (is_array($objectives)) {
            foreach ($objectives as $o) {
                if (is_array($o) && in_array($o['type'] ?? '', ['RACE_TIME', 'PACE_OVER_DISTANCE'], true)) {
                    $distance = max($distance, (int) ($o['target_distance_meters'] ?? 0));
                }
            }
        }

        $text = (string) ($snapshot['goal'] ?? '').' '.(string) ($snapshot['target_race_name'] ?? '');

        return Discipline::infer($text, $distance > 0 ? $distance : null);
    }
}
