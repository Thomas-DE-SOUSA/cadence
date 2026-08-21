<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class AssignSessionActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'string', 'max:40'],
            'activity_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function sessionDate(): string
    {
        return (string) $this->validated('date');
    }

    public function activityId(): ?string
    {
        $value = $this->validated('activity_id');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
