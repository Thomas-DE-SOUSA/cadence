<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Request;

use Cadence\Activity\Application\UseCase\RecordActivity\BestEffortInput;
use Cadence\Activity\Application\UseCase\RecordActivity\SplitInput;
use Cadence\Activity\Application\UseCase\UpdateActivity\UpdateActivityInput;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'occurred_at' => ['required', 'date'],
            'distance_meters' => ['required', 'integer', 'min:1'],
            'moving_seconds' => ['required', 'integer', 'min:1'],
            'elapsed_seconds' => ['required', 'integer', 'min:1'],
            'elevation_gain_meters' => ['required', 'integer'],
            'splits' => ['array'],
            'splits.*.index' => ['required', 'integer', 'min:1'],
            'splits.*.distance_meters' => ['required', 'integer', 'min:1'],
            'splits.*.duration_seconds' => ['required', 'integer', 'min:1'],
            'splits.*.elevation_meters' => ['required', 'integer'],
            'best_efforts' => ['array'],
            'best_efforts.*.label' => ['required', 'string', 'max:32'],
            'best_efforts.*.distance_meters' => ['required', 'integer', 'min:1'],
            'best_efforts.*.duration_seconds' => ['required', 'integer', 'min:1'],
            'best_efforts.*.is_personal_record' => ['required', 'boolean'],
        ];
    }

    public function toInput(string $activityId): UpdateActivityInput
    {
        /** @var array<int, array<string, mixed>> $splits */
        $splits = $this->validated('splits') ?? [];
        /** @var array<int, array<string, mixed>> $bestEfforts */
        $bestEfforts = $this->validated('best_efforts') ?? [];

        return new UpdateActivityInput(
            activityId: $activityId,
            occurredAt: (string) $this->validated('occurred_at'),
            distanceMeters: (int) $this->validated('distance_meters'),
            movingSeconds: (int) $this->validated('moving_seconds'),
            elapsedSeconds: (int) $this->validated('elapsed_seconds'),
            elevationGainMeters: (int) $this->validated('elevation_gain_meters'),
            splits: array_values(array_map(static fn (array $s): SplitInput => new SplitInput(
                (int) $s['index'],
                (int) $s['distance_meters'],
                (int) $s['duration_seconds'],
                (int) $s['elevation_meters'],
            ), $splits)),
            bestEfforts: array_values(array_map(static fn (array $b): BestEffortInput => new BestEffortInput(
                (string) $b['label'],
                (int) $b['distance_meters'],
                (int) $b['duration_seconds'],
                (bool) $b['is_personal_record'],
            ), $bestEfforts)),
        );
    }
}
