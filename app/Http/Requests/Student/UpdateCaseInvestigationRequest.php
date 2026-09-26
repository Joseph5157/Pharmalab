<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCaseInvestigationRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('investigation')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->syncEnvelopeRules(),
            'test_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'result_type' => ['sometimes', 'nullable', Rule::in(['numeric', 'qualitative', 'narrative'])],
            'result_value' => ['sometimes', 'nullable', 'string', 'max:255'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'unit_not_stated' => ['sometimes', 'boolean'],
            'reference_range' => ['sometimes', 'nullable', 'string', 'max:120'],
            'reference_range_not_provided' => ['sometimes', 'boolean'],
            'reported_flag' => ['sometimes', 'nullable', Rule::in(['low', 'normal', 'high', 'critical', 'not_stated'])],
            'observed_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'interpretation' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
