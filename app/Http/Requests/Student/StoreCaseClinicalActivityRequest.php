<?php

namespace App\Http\Requests\Student;

use App\Enums\ClinicalActivityType;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCaseClinicalActivityRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_operation_id' => ['required', 'uuid'],
            'activity_type' => ['required', Rule::in([ClinicalActivityType::Intervention->value, ClinicalActivityType::Monitoring->value])],
            'status' => ['nullable', 'string', 'max:30'],
            'details' => ['nullable', 'array:problem,recommendation,recipient,communication_method,case_date,outcome,follow_up,parameter,result,observed_on,notes'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
