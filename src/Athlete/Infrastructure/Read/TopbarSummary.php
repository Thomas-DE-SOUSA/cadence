<?php

declare(strict_types=1);

namespace Cadence\Athlete\Infrastructure\Read;

use Cadence\Athlete\Domain\Port\AthleteRepository;
use Cadence\Shared\Domain\TenantId;
use DateTimeImmutable;

/**
 * Lightweight athlete summary shared with every page to power the top navigation
 * bar: display name/initial and a countdown to the next race.
 */
final class TopbarSummary
{
    /**
     * @param array{label:string,raceName:string|null,raceDate:string|null,distanceMeters:int,targetSeconds:int}|null $programGoal
     *
     * @return array{name:string,initial:string,raceName:string|null,raceDaysLeft:int|null}
     */
    public static function build(
        AthleteRepository $athletes,
        TenantId $tenant,
        ?array $programGoal,
        DateTimeImmutable $today,
    ): array {
        $profile = $athletes->ofTenant($tenant)?->profile();

        $name = $profile !== null && trim($profile->displayName) !== '' ? trim($profile->displayName) : 'Athlète';

        // The race prefers the profile, then the program.
        $raceName = ($profile !== null ? $profile->raceName : null) ?? ($programGoal['raceName'] ?? null);
        $raceDate = ($profile !== null ? $profile->raceDate : null) ?? ($programGoal['raceDate'] ?? null);

        $daysLeft = null;
        if ($raceDate !== null && trim($raceDate) !== '') {
            $race = new DateTimeImmutable(substr($raceDate, 0, 10));
            $daysLeft = (int) $today->setTime(0, 0)->diff($race->setTime(0, 0))->format('%r%a');
        }

        return [
            'name' => $name,
            'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
            'raceName' => $raceName,
            'raceDaysLeft' => $daysLeft,
        ];
    }
}
