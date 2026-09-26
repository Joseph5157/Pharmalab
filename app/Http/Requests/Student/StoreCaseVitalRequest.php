<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCaseVitalRequest extends FormRequest
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
            'observation_type' => ['nullable', 'string', 'max:40'],
            'value_numeric' => ['nullable', 'numeric'],
            'value_text' => ['nullable', 'string', 'max:60'],
            'value_systolic' => ['nullable', 'integer', 'min:40', 'max:300'],
            'value_diastolic' => ['nullable', 'integer', 'min:20', 'max:200'],
            'unit' => ['nullable', 'string', 'max:20'],
            'observed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['nullable', 'date_format:H:i'],
            'source' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);

        $validator->after(function (Validator $validator): void {
            $type = $this->input('observation_type');

            // BP must be entered as a pair, per field catalogue §5 ("Systolic
            // and diastolic pressure are entered together") — the check is
            // symmetric so a partial pair fails on whichever side is missing.
            if ($type === 'blood_pressure') {
                if ($this->filled('value_systolic') && ! $this->filled('value_diastolic')) {
                    $validator->errors()->add('value_diastolic', 'Diastolic pressure is required when systolic pressure is recorded.');
                }
                if ($this->filled('value_diastolic') && ! $this->filled('value_systolic')) {
                    $validator->errors()->add('value_systolic', 'Systolic pressure is required when diastolic pressure is recorded.');
                }
            }

            // SpO2 accepts 0-100 only (field catalogue §5), independent of
            // the generic numeric-vital rule above which has no fixed range.
            if ($type === 'oxygen_saturation' && $this->filled('value_numeric')) {
                $value = (float) $this->input('value_numeric');
                if ($value < 0 || $value > 100) {
                    $validator->errors()->add('value_numeric', 'Oxygen saturation must be between 0 and 100.');
                }
            }
        });
    }
}
