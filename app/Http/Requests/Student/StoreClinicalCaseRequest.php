<?php

namespace App\Http\Requests\Student;

use App\Models\ClinicalCase;
use App\Models\RotationAssignment;
use App\Services\RotationCategoryMatcher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClinicalCaseRequest extends FormRequest
{
    private ?string $canonicalCategory = null;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ClinicalCase::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $institutionId = $this->user()->institution_id;

        return [
            'rotation_assignment_id' => [
                'required',
                Rule::exists('rotation_assignments', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)->where('student_id', $this->user()->id)->where('status', 'active')),
            ],
            'encounter_date' => ['nullable', 'date'],
            'case_category' => ['nullable', 'string', 'max:80'],
            'age_value' => ['nullable', 'integer', 'min:0', 'max:150'],
            'age_unit' => ['nullable', 'string', 'max:20'],
            'sex' => ['nullable', Rule::in(['male', 'female', 'intersex', 'unknown'])],
            'clinical_site_id' => ['nullable', Rule::exists('clinical_sites', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId))],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId))],
            'ward_id' => ['nullable', Rule::exists('wards', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId))],
            'care_setting' => ['nullable', Rule::in(['inpatient', 'outpatient', 'emergency', 'other'])],
            'hospital_day_at_first_review' => ['nullable', 'integer', 'min:1', 'max:999'],
            'information_source' => ['nullable', 'string', 'max:60'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'pregnancy_lactation_status' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** Same category rule as UpdateClinicalCaseContextRequest, so a raw create request cannot bypass it. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $input = $this->input('case_category');
            if ($validator->errors()->isNotEmpty() || ! is_string($input) || $input === '') {
                return;
            }

            $assignmentId = $this->input('rotation_assignment_id');
            $assignment = is_string($assignmentId) ? RotationAssignment::query()->whereKey($assignmentId)->first() : null;
            if ($assignment === null) {
                return;
            }

            $matcher = app(RotationCategoryMatcher::class);
            $options = $matcher->options($assignment->rotation_id);
            if ($options === []) {
                return;
            }

            $this->canonicalCategory = $matcher->canonical($assignment->rotation_id, $input);
            if ($this->canonicalCategory === null) {
                $validator->errors()->add('case_category', 'Choose one of this rotation\'s categories: '.implode(', ', $options).'.');
            }
        });
    }

    /** @return array<string, mixed> */
    public function caseAttributes(): array
    {
        $data = $this->validated();
        if ($this->canonicalCategory !== null) {
            $data['case_category'] = $this->canonicalCategory;
        }

        return $data;
    }
}
