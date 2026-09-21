<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'institution_id', 'actor_id', 'actor_role', 'event_type',
        'auditable_type', 'auditable_id', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
