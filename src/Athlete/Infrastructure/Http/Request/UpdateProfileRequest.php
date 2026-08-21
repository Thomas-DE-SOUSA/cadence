<?php

declare(strict_types=1);

namespace Cadence\Athlete\Infrastructure\Http\Request;

use Cadence\Athlete\Application\UseCase\SaveProfile\SaveProfileInput;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:80'],
            'birth_date' => ['nullable', 'date'],
            'height_cm' => ['nullable', 'integer', 'min:80', 'max:260'],
            'weight_kg' => ['nullable', 'numeric', 'min:20', 'max:300'],
            'resting_hr' => ['nullable', 'integer', 'min:25', 'max:120'],
            'max_hr' => ['nullable', 'integer', 'min:120', 'max:230'],
            'sessions_per_week' => ['nullable', 'integer', 'min:0', 'max:14'],
            'weekly_volume_km' => ['nullable', 'integer', 'min:0', 'max:400'],
            'preferred_days' => ['array'],
            'preferred_days.*' => ['integer', 'min:1', 'max:7'],
            'race_name' => ['nullable', 'string', 'max:120'],
            'race_date' => ['nullable', 'date'],
            'goal_distance_km' => ['nullable', 'numeric', 'min:0.1', 'max:500'],
            'goal_time' => ['nullable', 'string', 'max:12'],
            'long_term_goal' => ['nullable', 'string', 'max:500'],
            'units' => ['nullable', 'string', 'in:metric'],
            'week_start' => ['nullable', 'string', 'in:monday,sunday'],
            'theme' => ['nullable', 'string', 'in:light'],
            'session_reminders' => ['boolean'],
        ];
    }

    public function toInput(): SaveProfileInput
    {
        $goalKm = $this->validated('goal_distance_km');
        $goalDistanceMeters = is_numeric($goalKm) ? (int) round(((float) $goalKm) * 1000) : null;
        $goalSeconds = self::parseTime($this->nullableString('goal_time'));

        /** @var list<int> $preferredDays */
        $preferredDays = array_map('intval', (array) $this->validated('preferred_days', []));

        return new SaveProfileInput(
            displayName: (string) ($this->nullableString('display_name') ?? ''),
            birthDate: $this->nullableString('birth_date'),
            heightCm: self::nullableInt($this->validated('height_cm')),
            weightKg: self::nullableFloat($this->validated('weight_kg')),
            restingHr: self::nullableInt($this->validated('resting_hr')),
            maxHr: self::nullableInt($this->validated('max_hr')),
            sessionsPerWeek: self::nullableInt($this->validated('sessions_per_week')),
            weeklyVolumeKm: self::nullableInt($this->validated('weekly_volume_km')),
            preferredDays: $preferredDays,
            raceName: $this->nullableString('race_name'),
            raceDate: $this->nullableString('race_date'),
            goalDistanceMeters: $goalDistanceMeters,
            goalTargetSeconds: $goalSeconds,
            longTermGoal: $this->nullableString('long_term_goal'),
            units: (string) ($this->nullableString('units') ?? 'metric'),
            weekStart: (string) ($this->nullableString('week_start') ?? 'monday'),
            theme: (string) ($this->nullableString('theme') ?? 'light'),
            sessionReminders: $this->boolean('session_reminders'),
        );
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /** Parse "mm:ss" or "h:mm:ss" into seconds; null when empty/invalid. */
    private static function parseTime(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $parts = array_map('intval', explode(':', $value));
        $seconds = 0;
        foreach ($parts as $part) {
            $seconds = $seconds * 60 + $part;
        }

        return $seconds > 0 ? $seconds : null;
    }
}
