<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Request;

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
        ];
    }

    public function toInput(string $activityId): UpdateActivityInput
    {
        return new UpdateActivityInput(
            activityId: $activityId,
            occurredAt: (string) $this->validated('occurred_at'),
            distanceMeters: (int) $this->validated('distance_meters'),
            movingSeconds: (int) $this->validated('moving_seconds'),
            elapsedSeconds: (int) $this->validated('elapsed_seconds'),
            elevationGainMeters: (int) $this->validated('elevation_gain_meters'),
        );
    }
}
