<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Request;

use Cadence\Training\Application\UseCase\GenerateCycle\GenerateCycleInput;
use Illuminate\Foundation\Http\FormRequest;

final class GenerateCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'weeks' => ['required', 'integer', 'min:1', 'max:6'],
            'ressenti' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toInput(string $programId): GenerateCycleInput
    {
        $startDate = $this->validated('start_date');

        return new GenerateCycleInput(
            programId: $programId,
            startDate: is_string($startDate) ? $startDate : '',
            weeks: (int) $this->validated('weeks'),
            ressenti: (string) ($this->validated('ressenti') ?? ''),
        );
    }
}
