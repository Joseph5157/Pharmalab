<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncCaseDraftNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('caseDraftNote')) ?? false;
    }

    public function rules(): array
    {
        return [
            'client_operation_id' => ['required', 'uuid'],
            'base_lock_version' => ['required', 'integer', 'min:0'],
            'content' => ['present', 'nullable', 'string', 'max:50000'],
            'resolution' => ['nullable', Rule::in(['use_server', 'keep_local_copy', 'replace_server'])],
            'confirmed' => ['required_if:resolution,replace_server', 'boolean'],
        ];
    }
}
