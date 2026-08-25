<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class RescheduleSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'string', 'date'],
            'to' => ['required', 'string', 'date'],
        ];
    }

    public function fromDate(): string
    {
        return substr((string) $this->validated('from'), 0, 10);
    }

    public function toDate(): string
    {
        return substr((string) $this->validated('to'), 0, 10);
    }
}
