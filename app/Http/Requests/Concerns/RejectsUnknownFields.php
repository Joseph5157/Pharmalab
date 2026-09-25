<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Str;

trait RejectsUnknownFields
{
    protected function rejectUnknownFields(Validator $validator): void
    {
        $allowed = collect(array_keys($this->rules()))->map(fn (string $key) => explode('.', Str::before($key, '.*'))[0])->unique()->all();
        $unknown = array_diff(array_keys($this->all()), $allowed);
        if ($unknown === []) return;
        $validator->after(function (Validator $validator) use ($unknown): void { foreach ($unknown as $key) $validator->errors()->add($key, "The {$key} field is not recognized."); });
    }
}
