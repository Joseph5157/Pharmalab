<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaseVitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'observation_type' => ['required', 'string', 'max:40'],
            'value_numeric' => ['nullable', 'numeric'],
            'value_text' => ['nullable', 'string', 'max:60'],
            'unit' => ['nullable', 'string', 'max:20'],
            'observed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['nullable', 'date_format:H:i'],
            'source' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
