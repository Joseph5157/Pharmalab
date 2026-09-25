<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseClinicalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'chief_complaints' => ['nullable', 'array'],
            'chief_complaints.*.complaint' => ['required_with:chief_complaints', 'string', 'max:255'],
            'chief_complaints.*.duration' => ['nullable', 'string', 'max:60'],
            'history_present_illness' => ['nullable', 'string', 'max:5000'],
            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*.label' => ['required_with:diagnoses', 'string', 'max:255'],
            'diagnoses.*.type' => ['nullable', Rule::in(['provisional', 'confirmed', 'comorbidity'])],
            'past_medical_history' => ['nullable', 'string', 'max:5000'],
            'past_medical_history_none' => ['boolean'],
            'past_surgical_history' => ['nullable', 'string', 'max:5000'],
            'adherence_status' => ['nullable', Rule::in(['adherent', 'partially_adherent', 'non_adherent', 'unable_to_assess'])],
            'family_history' => ['nullable', 'string', 'max:5000'],
            'substance_history' => ['nullable', 'string', 'max:5000'],
            'examination_findings' => ['nullable', 'string', 'max:5000'],
            'allergy_status' => ['required', Rule::in(['no_known_allergy', 'known_allergy', 'unknown'])],
            'allergy_substance' => ['nullable', 'required_if:allergy_status,known_allergy', 'string', 'max:1000'],
            'allergy_reaction' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
