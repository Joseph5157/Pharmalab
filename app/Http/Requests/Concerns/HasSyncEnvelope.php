<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Arr;

trait HasSyncEnvelope
{
    /** @return array<string, mixed> */
    protected function syncEnvelopeRules(): array { return ['client_operation_id' => ['required', 'uuid'], 'base_lock_version' => ['required', 'integer', 'min:0'], 'resolution' => ['nullable', 'in:use_server,keep_local_copy,replace_server'], 'confirmed' => ['boolean']]; }
    /** @return array{client_operation_id:string,base_lock_version:int,resolution:string|null,confirmed:bool} */
    public function syncEnvelope(): array { return ['client_operation_id' => (string) $this->input('client_operation_id'), 'base_lock_version' => (int) $this->input('base_lock_version'), 'resolution' => $this->input('resolution'), 'confirmed' => $this->boolean('confirmed')]; }
    /** @return array<string, mixed> */
    public function sectionData(): array { return Arr::except($this->validated(), ['client_operation_id', 'base_lock_version', 'resolution', 'confirmed']); }
}
