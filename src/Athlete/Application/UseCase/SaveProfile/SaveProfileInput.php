<?php

declare(strict_types=1);

namespace Cadence\Athlete\Application\UseCase\SaveProfile;

use Cadence\Athlete\Domain\ValueObject\ProfileData;

final readonly class SaveProfileInput
{
    /** @param list<int> $preferredDays */
    public function __construct(
        public string $displayName,
        public ?string $birthDate,
        public ?int $heightCm,
        public ?float $weightKg,
        public ?int $restingHr,
        public ?int $maxHr,
        public ?int $sessionsPerWeek,
        public ?int $weeklyVolumeKm,
        public array $preferredDays,
        public ?string $raceName,
        public ?string $raceDate,
        public ?int $goalDistanceMeters,
        public ?int $goalTargetSeconds,
        public ?string $longTermGoal,
        public string $units = 'metric',
        public string $weekStart = 'monday',
        public string $theme = 'light',
        public bool $sessionReminders = true,
    ) {
    }

    public function toProfileData(): ProfileData
    {
        $days = array_values(array_unique(array_filter($this->preferredDays, static fn (int $d): bool => $d >= 1 && $d <= 7)));
        sort($days);

        return new ProfileData(
            trim($this->displayName),
            $this->birthDate,
            $this->heightCm,
            $this->weightKg,
            $this->restingHr,
            $this->maxHr,
            $this->sessionsPerWeek,
            $this->weeklyVolumeKm,
            $days,
            $this->raceName,
            $this->raceDate,
            $this->goalDistanceMeters,
            $this->goalTargetSeconds,
            $this->longTermGoal,
            $this->units,
            $this->weekStart,
            $this->theme,
            $this->sessionReminders,
        );
    }
}
