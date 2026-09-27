<?php

namespace App\Http\Requests\Student;

use App\Enums\ClinicalActivityType;
use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\CaseClinicalActivity;
use App\Models\ClinicalCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCaseClinicalActivityRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    public function authorize(): bool
    {
        $activity = $this->activity();
        $case = $this->route('case');
        $case = $case instanceof ClinicalCase ? $case : null;

        // rules() branches on activity_type to pick this row's field
        // allow-list, and that branch assumes the row is already known to be
        // Intervention or Monitoring. Validation runs before the controller
        // body's own wrong-door guard, so an ADR/Counselling row reaching this
        // route must be rejected here — otherwise its details get validated
        // against the wrong field set and fail with a misleading 422 instead
        // of the intended 404 (see Task 3/5's wrong-door Review Focus item).
        abort_unless(
            $activity !== null && $case !== null && $activity->clinical_case_id === $case->id
                && in_array($activity->activity_type, [ClinicalActivityType::Intervention, ClinicalActivityType::Monitoring], true),
            404,
        );

        return $this->user()?->can('update', $activity) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            ...$this->syncEnvelopeRules(),
            'status' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];

        if ($this->activity()?->activity_type === ClinicalActivityType::Intervention) {
            return [
                ...$rules,
                'details' => ['sometimes', 'nullable', 'array:problem,recommendation,recipient,communication_method,case_date,outcome,follow_up'],
                'details.problem' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'details.recommendation' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'details.recipient' => ['sometimes', 'nullable', 'string', 'max:120'],
                'details.communication_method' => ['sometimes', 'nullable', 'string', 'max:60'],
                'details.case_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
                'details.outcome' => ['sometimes', 'nullable', Rule::in(['accepted', 'partially_accepted', 'not_accepted', 'pending', 'not_communicated'])],
                'details.follow_up' => ['sometimes', 'nullable', 'string', 'max:1000'],
            ];
        }

        return [
            ...$rules,
            'details' => ['sometimes', 'nullable', 'array:parameter,result,observed_on,notes'],
            'details.parameter' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.result' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.observed_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'details.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }

    private function activity(): ?CaseClinicalActivity
    {
        $activity = $this->route('activity');

        return $activity instanceof CaseClinicalActivity ? $activity : null;
    }
}
