<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClinicalCaseContextRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;
    private const AGE_MAX_BY_UNIT = ['days' => 364, 'months' => 59, 'years' => 120];
    public function authorize(): bool { return $this->user()?->can('update', $this->route('case')) ?? false; }
    /** @return array<string, mixed> */
    public function rules(): array { return [...$this->syncEnvelopeRules(), 'encounter_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'], 'case_category' => ['sometimes', 'nullable', 'string', 'max:80'], 'age_value' => ['sometimes', 'nullable', 'integer', 'min:0'], 'age_unit' => ['sometimes', 'nullable', Rule::in(['days', 'months', 'years'])], 'sex' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'intersex', 'unknown'])], 'care_setting' => ['sometimes', 'nullable', Rule::in(['inpatient', 'outpatient', 'emergency', 'other'])], 'hospital_day_at_first_review' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'], 'information_source' => ['sometimes', 'nullable', 'string', 'max:60'], 'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:500'], 'height_cm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:300'], 'pregnancy_lactation_status' => ['sometimes', 'nullable', 'string', 'max:30']]; }
    public function withValidator(Validator $validator): void { $this->rejectUnknownFields($validator); $validator->after(function (Validator $validator): void { if (! $this->has('age_value') || $this->input('age_value') === null) return; $max = self::AGE_MAX_BY_UNIT[$this->input('age_unit')] ?? null; if ($max !== null && (int) $this->input('age_value') > $max) $validator->errors()->add('age_value', "The age value must not exceed {$max}."); }); }
}
