<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\ClinicalCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMedicationChartAvailabilityRequest extends FormRequest
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
            'medication_chart_status' => ['sometimes', 'nullable', Rule::in(['documented', 'none_documented'])],
            // No 'sometimes': Laravel's `sometimes` skips *all* rules for a
            // key entirely absent from the request, including implicit ones
            // like required_if — so a client that simply never sends this
            // key could set medication_chart_status to none_documented with
            // no reason at all. Dropping it makes required_if evaluate
            // regardless of presence (Laravel's own $this->getData() lookup
            // in validated() still excludes a genuinely-absent key from the
            // output either way, so this doesn't force null onto unrelated
            // partial updates that never touch this field).
            'medication_chart_none_reason' => ['nullable', 'required_if:medication_chart_status,none_documented', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);

        $validator->after(function (Validator $validator): void {
            if ($this->input('medication_chart_status') !== 'none_documented') {
                return;
            }

            $case = $this->route('case');
            if (! $case instanceof ClinicalCase) {
                return;
            }
            if ($case->medications()->exists()) {
                $validator->errors()->add('medication_chart_status', 'Remove the recorded medicines before marking no current medicines documented.');
            }
        });
    }
}
