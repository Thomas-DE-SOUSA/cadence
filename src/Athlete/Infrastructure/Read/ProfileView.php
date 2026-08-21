<?php

declare(strict_types=1);

namespace Cadence\Athlete\Infrastructure\Read;

use Cadence\Athlete\Domain\Model\Athlete;
use Cadence\Athlete\Domain\ValueObject\ProfileData;
use DateTimeImmutable;

/**
 * Assembles the Profil screen: the editable form values plus read-only derived
 * data (age, current VDOT, heart-rate zones and the resolved race goal).
 */
final class ProfileView
{
    /** Heart-rate zones as [key, label, low %, high %] of reserve/max. */
    private const HR_ZONES = [
        ['z1', 'Z1 · Récupération', 0.50, 0.60],
        ['z2', 'Z2 · Endurance', 0.60, 0.70],
        ['z3', 'Z3 · Tempo', 0.70, 0.80],
        ['z4', 'Z4 · Seuil', 0.80, 0.90],
        ['z5', 'Z5 · VO₂max', 0.90, 1.00],
    ];

    /**
     * @param array{label:string,raceName:string|null,raceDate:string|null,distanceMeters:int,targetSeconds:int}|null $programGoal
     *
     * @return array<string, mixed>
     */
    public static function build(
        ?Athlete $athlete,
        ?float $vdot,
        ?string $basisLabel,
        ?array $programGoal,
        DateTimeImmutable $today,
    ): array {
        $profile = $athlete?->profile();

        return [
            'profile' => self::formValues($profile, $programGoal),
            'derived' => [
                'age' => self::age($profile?->birthDate, $today),
                'vdot' => $vdot,
                'basisLabel' => $basisLabel,
                'hrZones' => self::hrZones($profile),
                'goal' => self::resolvedGoal($profile, $programGoal),
                'hasProfile' => $athlete !== null,
            ],
        ];
    }

    /**
     * @param array{label:string,raceName:string|null,raceDate:string|null,distanceMeters:int,targetSeconds:int}|null $programGoal
     *
     * @return array<string, mixed>
     */
    private static function formValues(?ProfileData $p, ?array $programGoal): array
    {
        // The goal prefills from the program only while the profile has none of its own.
        $goalDistance = ($p !== null ? $p->goalDistanceMeters : null) ?? $programGoal['distanceMeters'] ?? null;
        $goalSeconds = ($p !== null ? $p->goalTargetSeconds : null) ?? $programGoal['targetSeconds'] ?? null;
        $raceName = ($p !== null ? $p->raceName : null) ?? $programGoal['raceName'] ?? null;
        $raceDate = ($p !== null ? $p->raceDate : null) ?? $programGoal['raceDate'] ?? null;

        return [
            'displayName' => $p !== null ? $p->displayName : '',
            'birthDate' => $p?->birthDate,
            'heightCm' => $p?->heightCm,
            'weightKg' => $p?->weightKg,
            'restingHr' => $p?->restingHr,
            'maxHr' => $p?->maxHr,
            'sessionsPerWeek' => $p?->sessionsPerWeek,
            'weeklyVolumeKm' => $p?->weeklyVolumeKm,
            'preferredDays' => $p !== null ? $p->preferredDays : [],
            'raceName' => $raceName,
            'raceDate' => $raceDate,
            'goalDistanceKm' => $goalDistance !== null ? round($goalDistance / 1000, 2) : null,
            'goalTime' => $goalSeconds !== null ? self::mmss($goalSeconds) : null,
            'longTermGoal' => $p?->longTermGoal,
            'units' => $p !== null ? $p->units : 'metric',
            'weekStart' => $p !== null ? $p->weekStart : 'monday',
            'theme' => $p !== null ? $p->theme : 'light',
            'sessionReminders' => $p !== null ? $p->sessionReminders : true,
        ];
    }

    /**
     * @param array{label:string,raceName:string|null,raceDate:string|null,distanceMeters:int,targetSeconds:int}|null $programGoal
     *
     * @return array{label:string,distanceMeters:int,targetSeconds:int,paceSeconds:int,raceName:string|null,raceDate:string|null}|null
     */
    private static function resolvedGoal(?ProfileData $p, ?array $programGoal): ?array
    {
        if ($p !== null && $p->goalDistanceMeters !== null && $p->goalTargetSeconds !== null) {
            $distance = $p->goalDistanceMeters;
            $seconds = $p->goalTargetSeconds;
            $label = self::goalLabel($distance, $seconds);

            return [
                'label' => $label,
                'distanceMeters' => $distance,
                'targetSeconds' => $seconds,
                'paceSeconds' => (int) round($seconds / ($distance / 1000)),
                'raceName' => $p->raceName,
                'raceDate' => $p->raceDate,
            ];
        }

        if ($programGoal !== null) {
            $distance = $programGoal['distanceMeters'];
            $seconds = $programGoal['targetSeconds'];

            return [
                'label' => $programGoal['label'],
                'distanceMeters' => $distance,
                'targetSeconds' => $seconds,
                'paceSeconds' => (int) round($seconds / ($distance / 1000)),
                'raceName' => $programGoal['raceName'],
                'raceDate' => $programGoal['raceDate'],
            ];
        }

        return null;
    }

    /**
     * @return list<array{key:string,label:string,minBpm:int,maxBpm:int}>|null
     */
    private static function hrZones(?ProfileData $p): ?array
    {
        if ($p === null || $p->maxHr === null) {
            return null;
        }
        $max = $p->maxHr;
        $rest = $p->restingHr;

        $bpm = static fn (float $pct): int => $rest !== null
            ? (int) round($rest + $pct * ($max - $rest))
            : (int) round($pct * $max);

        $zones = [];
        foreach (self::HR_ZONES as [$key, $label, $lo, $hi]) {
            $zones[] = ['key' => $key, 'label' => $label, 'minBpm' => $bpm($lo), 'maxBpm' => $bpm($hi)];
        }

        return $zones;
    }

    private static function age(?string $birthDate, DateTimeImmutable $today): ?int
    {
        if ($birthDate === null || trim($birthDate) === '') {
            return null;
        }

        return (int) $today->diff(new DateTimeImmutable($birthDate))->y;
    }

    private static function goalLabel(int $distanceMeters, int $seconds): string
    {
        $distance = $distanceMeters % 1000 === 0 ? intdiv($distanceMeters, 1000).' km' : number_format($distanceMeters / 1000, 2, ',', ' ').' km';

        return "{$distance} sous ".self::mmss($seconds);
    }

    private static function mmss(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $s)
            : sprintf('%d:%02d', $m, $s);
    }
}
