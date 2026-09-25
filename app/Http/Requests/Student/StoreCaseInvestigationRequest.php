<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseInvestigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'test_name' => ['required', 'string', 'max:120'],
            'result_type' => ['required', Rule::in(['numeric', 'qualitative', 'narrative'])],
            'result_value' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'reference_range' => ['nullable', 'string', 'max:120'],
            'reported_flag' => ['nullable', Rule::in(['low', 'normal', 'high', 'critical', 'not_stated'])],
            'observed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['nullable', 'date_format:H:i'],
            'interpretation' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
