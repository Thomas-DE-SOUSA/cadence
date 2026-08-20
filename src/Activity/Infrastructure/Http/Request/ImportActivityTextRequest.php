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
        ];
    }

    public function pastedText(): string
    {
        return (string) $this->validated('text');
    }
}
