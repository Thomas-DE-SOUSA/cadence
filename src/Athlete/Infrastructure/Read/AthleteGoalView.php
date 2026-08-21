<?php

declare(strict_types=1);

namespace Cadence\Athlete\Infrastructure\Read;

use Cadence\Athlete\Domain\Port\AthleteRepository;
use Cadence\Shared\Domain\TenantId;

/**
 * Exposes the athlete's personal race goal in the shape the progression screen
 * expects, so a goal edited in the profile takes precedence over the program's.
 */
final class AthleteGoalView
{
    /**
     * @return array{label:string,raceName:string|null,raceDate:string|null,distanceMeters:int,targetSeconds:int}|null
     */
    public static function forTenant(AthleteRepository $athletes, TenantId $tenant): ?array
    {
        $athlete = $athletes->ofTenant($tenant);
        if ($athlete === null) {
            return null;
        }

        $profile = $athlete->profile();
        $distance = $profile->goalDistanceMeters;
        $seconds = $profile->goalTargetSeconds;
        if ($distance === null || $seconds === null) {
            return null;
        }

        $distanceLabel = $distance % 1000 === 0
            ? intdiv($distance, 1000).' km'
            : number_format($distance / 1000, 2, ',', ' ').' km';
        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;
        $timeLabel = $minutes.':'.str_pad((string) $rest, 2, '0', STR_PAD_LEFT);

        return [
            'label' => "{$distanceLabel} sous {$timeLabel}",
            'raceName' => $profile->raceName,
            'raceDate' => $profile->raceDate,
            'distanceMeters' => $distance,
            'targetSeconds' => $seconds,
        ];
    }
}
