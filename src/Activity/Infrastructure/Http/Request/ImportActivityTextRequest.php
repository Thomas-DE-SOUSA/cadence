<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class ImportActivityTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:20', 'max:20000'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    public function pastedText(): string
    {
        return (string) $this->validated('text');
    }

    /** Optional user-provided date that overrides the one parsed from the text. */
    public function occurredAtOverride(): ?string
    {
        $value = $this->validated('occurred_at');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
