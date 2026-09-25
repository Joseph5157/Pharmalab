<?php

namespace App\Http\Requests\Student;

use App\Enums\MedicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'medication_context' => ['nullable', Rule::in(['chart', 'history'])],
            'generic_name' => ['required', 'string', 'max:120'],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'indication' => ['nullable', 'string', 'max:255'],
            'dose_amount' => ['nullable', 'string', 'max:30'],
            'dose_unit' => ['nullable', 'string', 'max:20'],
            'dosage_form' => ['nullable', 'string', 'max:30'],
            'route' => ['nullable', 'string', 'max:30'],
            'frequency' => ['nullable', 'string', 'max:60'],
            'start_reference' => ['nullable', 'string', 'max:30'],
            'stop_reference' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(array_map(fn (MedicationStatus $s): string => $s->value, MedicationStatus::cases()))],
            'prn_indication' => ['nullable', 'required_if:status,prn', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
