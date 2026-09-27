<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSoapNoteRequest extends FormRequest
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
            'subjective' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'objective' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assessment' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'plan' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'monitoring_plan' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'monitoring_plan_not_applicable_reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'drug_related_problem_status' => ['sometimes', 'nullable', 'in:none_identified,identified,unable_to_assess'],
            'drug_related_problem_categories' => ['sometimes', 'nullable', 'array'],
            'drug_related_problem_categories.*' => ['in:untreated_indication,medicine_without_indication,ineffective_medicine,dose_too_low,dose_too_high,adr,interaction,non_adherence,duplication,administration_problem,monitoring_required,other'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
