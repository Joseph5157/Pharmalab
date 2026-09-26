<?php

namespace App\Http\Requests\Student;

use App\Enums\MedicationStatus;
use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCaseMedicationRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('medication')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->syncEnvelopeRules(),
            'medication_context' => ['sometimes', 'nullable', Rule::in(['chart', 'history'])],
            'generic_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'indication' => ['sometimes', 'nullable', 'string', 'max:255'],
            'indication_unclear' => ['sometimes', 'boolean'],
            'dose_amount' => ['sometimes', 'nullable', 'string', 'max:30'],
            'dose_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'dosage_form' => ['sometimes', 'nullable', 'string', 'max:30'],
            'route' => ['sometimes', 'nullable', 'string', 'max:30'],
            'frequency' => ['sometimes', 'nullable', 'string', 'max:60'],
            'start_reference' => ['sometimes', 'nullable', 'string', 'max:30'],
            'stop_reference' => ['sometimes', 'nullable', 'required_if:status,stopped', 'required_if:status,completed', 'string', 'max:30'],
            'status' => ['sometimes', 'required', Rule::in(array_map(fn (MedicationStatus $s): string => $s->value, MedicationStatus::cases()))],
            'prn_indication' => ['sometimes', 'nullable', 'required_if:status,prn', 'string', 'max:120'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
