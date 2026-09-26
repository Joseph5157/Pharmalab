<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\CaseVital;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCaseVitalRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('vital')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->syncEnvelopeRules(),
            'observation_type' => ['sometimes', 'required', 'string', 'max:40'],
            'value_numeric' => ['sometimes', 'nullable', 'numeric'],
            'value_text' => ['sometimes', 'nullable', 'string', 'max:60'],
            'value_systolic' => ['sometimes', 'nullable', 'integer', 'min:40', 'max:300'],
            'value_diastolic' => ['sometimes', 'nullable', 'integer', 'min:20', 'max:200'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'observed_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'source' => ['sometimes', 'nullable', 'string', 'max:60'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);

        $validator->after(function (Validator $validator): void {
            $vital = $this->route('vital');
            $vital = $vital instanceof CaseVital ? $vital : null;
            $type = $this->has('observation_type') ? $this->input('observation_type') : $vital?->observation_type;

            if ($type === 'oxygen_saturation' && $this->filled('value_numeric')) {
                $value = (float) $this->input('value_numeric');
                if ($value < 0 || $value > 100) {
                    $validator->errors()->add('value_numeric', 'Oxygen saturation must be between 0 and 100.');
                }
            }
        });
    }
}
