<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAdrActivityRequest extends FormRequest
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
            'status' => ['sometimes', 'required', Rule::in(['yes', 'no', 'unable_to_assess'])],
            'details' => ['sometimes', 'nullable', 'array:event,onset_reference,stop_reference,suspected_medicine,dose_route_frequency,concomitant_medicines,relevant_tests,action_taken,seriousness,outcome,dechallenge,rechallenge'],
            'details.event' => ['required_if:status,yes', 'nullable', 'string', 'max:1000'],
            'details.onset_reference' => ['sometimes', 'nullable', 'string', 'max:30'],
            'details.stop_reference' => ['sometimes', 'nullable', 'string', 'max:30'],
            'details.suspected_medicine' => ['required_if:status,yes', 'nullable', 'string', 'max:255'],
            'details.dose_route_frequency' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.concomitant_medicines' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.relevant_tests' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.action_taken' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.seriousness' => ['sometimes', 'nullable', Rule::in(['serious', 'non_serious'])],
            'details.outcome' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.dechallenge' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.rechallenge' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
