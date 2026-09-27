<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCaseInvestigationRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_operation_id' => ['required', 'uuid'],
            'test_name' => ['nullable', 'string', 'max:120'],
            'result_type' => ['nullable', Rule::in(['numeric', 'qualitative', 'narrative'])],
            'result_value' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_not_stated' => ['sometimes', 'boolean'],
            'reference_range' => ['nullable', 'string', 'max:120'],
            'reference_range_not_provided' => ['sometimes', 'boolean'],
            'reported_flag' => ['nullable', Rule::in(['low', 'normal', 'high', 'critical', 'not_stated'])],
            'observed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['nullable', 'date_format:H:i'],
            'interpretation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
