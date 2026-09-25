<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCaseClinicalProfileRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->syncEnvelopeRules(),
            'chief_complaints' => ['sometimes', 'nullable', 'array'],
            'chief_complaints.*.complaint' => ['required_with:chief_complaints', 'string', 'max:255'],
            'chief_complaints.*.duration' => ['nullable', 'string', 'max:60'],
            'history_present_illness' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'diagnoses' => ['sometimes', 'nullable', 'array'],
            'diagnoses.*.label' => ['required_with:diagnoses', 'string', 'max:255'],
            'diagnoses.*.type' => ['nullable', Rule::in(['provisional', 'confirmed', 'comorbidity'])],
            'past_medical_history' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'past_medical_history_none' => ['sometimes', 'boolean'],
            'past_surgical_history' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'adherence_status' => ['sometimes', 'nullable', Rule::in(['adherent', 'partially_adherent', 'non_adherent', 'unable_to_assess'])],
            'family_history' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'substance_history' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'examination_findings' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'allergy_status' => ['sometimes', 'required', Rule::in(['no_known_allergy', 'known_allergy', 'unknown'])],
            'allergy_substance' => ['nullable', 'required_if:allergy_status,known_allergy', 'string', 'max:1000'],
            'allergy_reaction' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
