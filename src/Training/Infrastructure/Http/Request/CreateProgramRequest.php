<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Request;

use Cadence\Training\Application\UseCase\CreateProgram\CreateProgramInput;
use Cadence\Training\Application\UseCase\CreateProgram\ObjectiveInput;
use Cadence\Training\Domain\Enum\ObjectiveType;
use Cadence\Training\Domain\Enum\ProgramPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'goal' => ['nullable', 'string', 'max:500'],
            'target_race_name' => ['nullable', 'string', 'max:160'],
            'target_race_date' => ['nullable', 'date'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::enum(ProgramPriority::class)],
            'objectives' => ['array'],
            'objectives.*.type' => ['required', Rule::enum(ObjectiveType::class)],
            'objectives.*.label' => ['required', 'string', 'max:120'],
            'objectives.*.target_distance_meters' => ['nullable', 'integer', 'min:1'],
            'objectives.*.target_seconds' => ['nullable', 'integer', 'min:1'],
            'objectives.*.target_pace_seconds_per_km' => ['nullable', 'numeric', 'min:1'],
            'objectives.*.target_count' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toInput(): CreateProgramInput
    {
        /** @var array<int, array<string, mixed>> $objectives */
        $objectives = $this->validated('objectives') ?? [];

        return new CreateProgramInput(
            name: (string) $this->validated('name'),
            goal: (string) ($this->validated('goal') ?? ''),
            targetRaceName: (string) ($this->validated('target_race_name') ?? ''),
            targetRaceDate: $this->nullableString('target_race_date'),
            startDate: (string) $this->validated('start_date'),
            endDate: $this->nullableString('end_date'),
            priority: (string) $this->validated('priority'),
            objectives: array_values(array_map(static fn (array $o): ObjectiveInput => new ObjectiveInput(
                type: (string) $o['type'],
                label: (string) $o['label'],
                targetDistanceMeters: isset($o['target_distance_meters']) ? (int) $o['target_distance_meters'] : null,
                targetSeconds: isset($o['target_seconds']) ? (int) $o['target_seconds'] : null,
                targetPaceSecondsPerKm: isset($o['target_pace_seconds_per_km']) ? (float) $o['target_pace_seconds_per_km'] : null,
                targetCount: isset($o['target_count']) ? (int) $o['target_count'] : null,
            ), $objectives)),
        );
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
