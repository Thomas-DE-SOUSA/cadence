<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Request;

use Cadence\Activity\Application\UseCase\RecordActivity\BestEffortInput;
use Cadence\Activity\Application\UseCase\RecordActivity\RecordActivityInput;
use Cadence\Activity\Application\UseCase\RecordActivity\SplitInput;
use Cadence\Activity\Domain\Enum\ActivitySource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // tenant_id is intentionally NOT accepted here — tenancy is server-authoritative.
            'occurred_at' => ['required', 'date'],
            'source' => ['required', Rule::enum(ActivitySource::class)],
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

    public function toInput(): RecordActivityInput
    {
        /** @var array<int, array<string, mixed>> $splits */
        $splits = $this->validated('splits') ?? [];
        /** @var array<int, array<string, mixed>> $bestEfforts */
        $bestEfforts = $this->validated('best_efforts') ?? [];

        return new RecordActivityInput(
            occurredAt: (string) $this->validated('occurred_at'),
            source: (string) $this->validated('source'),
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
