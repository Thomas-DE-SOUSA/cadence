<?php

declare(strict_types=1);

namespace Cadence\Athlete\Domain\ValueObject;

use Cadence\Athlete\Domain\Exception\AthleteErrorCode;
use Cadence\Athlete\Domain\Exception\InvalidAthlete;

/**
 * The editable athlete profile: identity, physiology, training availability,
 * the personal goal and app settings. Validated on construction so an invalid
 * profile can never exist.
 *
 * @phpstan-type ProfileSnapshot array{display_name:string,birth_date:string|null,height_cm:int|null,weight_kg:float|null,resting_hr:int|null,max_hr:int|null,sessions_per_week:int|null,weekly_volume_km:int|null,preferred_days:list<int>,race_name:string|null,race_date:string|null,goal_distance_meters:int|null,goal_target_seconds:int|null,long_term_goal:string|null,units:string,week_start:string,theme:string,session_reminders:bool}
 */
final class ProfileData
{
    /** @param list<int> $preferredDays weekday numbers 1 (Mon) .. 7 (Sun) */
    public function __construct(
        public readonly string $displayName,
        public readonly ?string $birthDate,
        public readonly ?int $heightCm,
        public readonly ?float $weightKg,
        public readonly ?int $restingHr,
        public readonly ?int $maxHr,
        public readonly ?int $sessionsPerWeek,
        public readonly ?int $weeklyVolumeKm,
        public readonly array $preferredDays,
        public readonly ?string $raceName,
        public readonly ?string $raceDate,
        public readonly ?int $goalDistanceMeters,
        public readonly ?int $goalTargetSeconds,
        public readonly ?string $longTermGoal,
        public readonly string $units,
        public readonly string $weekStart,
        public readonly string $theme,
        public readonly bool $sessionReminders,
    ) {
        if ($heightCm !== null && ($heightCm < 80 || $heightCm > 260)) {
            throw new InvalidAthlete(AthleteErrorCode::INVALID_BODY_METRIC, 'Height must be between 80 and 260 cm.');
        }
        if ($weightKg !== null && ($weightKg < 20.0 || $weightKg > 300.0)) {
            throw new InvalidAthlete(AthleteErrorCode::INVALID_BODY_METRIC, 'Weight must be between 20 and 300 kg.');
        }
        if ($restingHr !== null && ($restingHr < 25 || $restingHr > 120)) {
            throw new InvalidAthlete(AthleteErrorCode::INVALID_HEART_RATE, 'Resting HR must be between 25 and 120 bpm.');
        }
        if ($maxHr !== null && ($maxHr < 120 || $maxHr > 230)) {
            throw new InvalidAthlete(AthleteErrorCode::INVALID_HEART_RATE, 'Max HR must be between 120 and 230 bpm.');
        }
        if ($restingHr !== null && $maxHr !== null && $restingHr >= $maxHr) {
            throw new InvalidAthlete(AthleteErrorCode::INVALID_HEART_RATE, 'Resting HR must be below max HR.');
        }
        if ($sessionsPerWeek !== null && ($sessionsPerWeek < 0 || $sessionsPerWeek > 14)) {
            throw new InvalidAthlete(AthleteErrorCode::INVALID_AVAILABILITY, 'Sessions per week must be between 0 and 14.');
        }
        if ($weeklyVolumeKm !== null && ($weeklyVolumeKm < 0 || $weeklyVolumeKm > 400)) {
            throw new InvalidAthlete(AthleteErrorCode::INVALID_AVAILABILITY, 'Weekly volume must be between 0 and 400 km.');
        }
        foreach ($preferredDays as $day) {
            if ($day < 1 || $day > 7) {
                throw new InvalidAthlete(AthleteErrorCode::INVALID_AVAILABILITY, 'Preferred days must be between 1 and 7.');
            }
        }
    }

    /** @return ProfileSnapshot */
    public function toSnapshot(): array
    {
        return [
            'display_name' => $this->displayName,
            'birth_date' => $this->birthDate,
            'height_cm' => $this->heightCm,
            'weight_kg' => $this->weightKg,
            'resting_hr' => $this->restingHr,
            'max_hr' => $this->maxHr,
            'sessions_per_week' => $this->sessionsPerWeek,
            'weekly_volume_km' => $this->weeklyVolumeKm,
            'preferred_days' => $this->preferredDays,
            'race_name' => $this->raceName,
            'race_date' => $this->raceDate,
            'goal_distance_meters' => $this->goalDistanceMeters,
            'goal_target_seconds' => $this->goalTargetSeconds,
            'long_term_goal' => $this->longTermGoal,
            'units' => $this->units,
            'week_start' => $this->weekStart,
            'theme' => $this->theme,
            'session_reminders' => $this->sessionReminders,
        ];
    }

    /** @param ProfileSnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            $s['display_name'],
            $s['birth_date'],
            $s['height_cm'],
            $s['weight_kg'],
            $s['resting_hr'],
            $s['max_hr'],
            $s['sessions_per_week'],
            $s['weekly_volume_km'],
            $s['preferred_days'],
            $s['race_name'],
            $s['race_date'],
            $s['goal_distance_meters'],
            $s['goal_target_seconds'],
            $s['long_term_goal'],
            $s['units'],
            $s['week_start'],
            $s['theme'],
            $s['session_reminders'],
        );
    }
}
