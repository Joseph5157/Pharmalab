<?php

namespace App\Http\Requests\Student;

use App\Enums\ClinicalActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseClinicalActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'activity_type' => ['required', Rule::in(array_map(fn (ClinicalActivityType $t): string => $t->value, ClinicalActivityType::cases()))],
            'status' => ['nullable', 'string', 'max:30'],
            'details' => ['nullable', 'array'],
        ];
    }
}
