<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\ClinicalCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvestigationsAvailabilityRequest extends FormRequest
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
            'investigations_status' => ['sometimes', 'nullable', Rule::in(['recorded', 'unavailable'])],
            // No 'sometimes': it would skip *all* rules for a key entirely
            // absent from the request, including implicit ones like
            // required_if — see UpdateMedicationChartAvailabilityRequest's
            // identical fix (PR #12 review) for the full rationale.
            'investigations_unavailable_reason' => ['nullable', 'required_if:investigations_status,unavailable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);

        $validator->after(function (Validator $validator): void {
            if ($this->input('investigations_status') !== 'unavailable') {
                return;
            }

            $case = $this->route('case');
            if (! $case instanceof ClinicalCase) {
                return;
            }
            if ($case->investigations()->exists()) {
                $validator->errors()->add('investigations_status', 'Remove the recorded investigations before marking this section unavailable.');
            }
        });
    }
}
