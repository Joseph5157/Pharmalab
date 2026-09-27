<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCounsellingActivityRequest extends FormRequest
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
            'status' => ['sometimes', 'required', Rule::in(['performed', 'planned', 'not_indicated', 'unable_to_perform'])],
            'details' => ['sometimes', 'nullable', 'array:topics,medicine_purpose,administration,adherence,precautions,adverse_effects,storage,lifestyle_follow_up,understanding_checked'],
            'details.topics' => ['required_if:status,performed', 'required_if:status,planned', 'nullable', 'string', 'max:2000'],
            'details.medicine_purpose' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.administration' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.adherence' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.precautions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.adverse_effects' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.storage' => ['sometimes', 'nullable', 'string', 'max:500'],
            'details.lifestyle_follow_up' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.understanding_checked' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
