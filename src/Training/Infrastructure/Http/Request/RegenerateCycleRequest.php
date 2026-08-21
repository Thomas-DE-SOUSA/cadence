<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Request;

use Cadence\Training\Application\UseCase\RegenerateCycle\RegenerateCycleInput;
use Illuminate\Foundation\Http\FormRequest;

final class RegenerateCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ressenti' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['nullable', 'date'],
        ];
    }

    public function toInput(string $programId, string $cycleId, string $athletePaces = ''): RegenerateCycleInput
    {
        $startDate = $this->validated('start_date');

        return new RegenerateCycleInput(
            programId: $programId,
            cycleId: $cycleId,
            ressenti: (string) ($this->validated('ressenti') ?? ''),
            startDate: is_string($startDate) ? $startDate : '',
            athletePaces: $athletePaces,
        );
    }
}
