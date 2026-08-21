<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Read;

use Cadence\Training\Infrastructure\Persistence\Eloquent\TrainingProgramModel;

/**
 * Read helper exposing the tenant's primary race goal (the RACE_TIME objective
 * plus the target race name/date) so the progression screen can track it
 * without reaching into the Training aggregates.
 */
final class RaceGoalView
{
    /**
     * @return array{label:string,raceName:string|null,raceDate:string|null,distanceMeters:int,targetSeconds:int}|null
     */
    public static function forTenant(string $tenantId): ?array
    {
        $program = TrainingProgramModel::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('start_date')
            ->first();
        if ($program === null) {
            return null;
        }

        /** @var mixed $objectives */
        $objectives = $program->objectives;
        foreach (is_array($objectives) ? $objectives : [] as $objective) {
            if (! is_array($objective) || ($objective['type'] ?? null) !== 'RACE_TIME') {
                continue;
            }
            $distance = (int) ($objective['target_distance_meters'] ?? 0);
            $target = (int) ($objective['target_seconds'] ?? 0);
            if ($distance <= 0 || $target <= 0) {
                continue;
            }

            $raceName = trim((string) $program->target_race_name);
            $raceDate = trim((string) $program->target_race_date);

            return [
                'label' => (string) ($objective['label'] ?? 'Objectif course'),
                'raceName' => $raceName !== '' ? $raceName : null,
                'raceDate' => $raceDate !== '' ? substr($raceDate, 0, 10) : null,
                'distanceMeters' => $distance,
                'targetSeconds' => $target,
            ];
        }

        return null;
    }
}
