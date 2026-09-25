<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SyncOperation extends Model
{
    use BelongsToInstitution, HasUlids;

    protected $fillable = [
        'institution_id', 'user_id', 'case_draft_note_id', 'syncable_type', 'syncable_id', 'client_operation_id',
        'section_key', 'base_lock_version', 'result_status', 'server_version',
    ];

    protected function casts(): array
    {
        return ['base_lock_version' => 'integer', 'server_version' => 'integer'];
    }

    /** @return MorphTo<Model, $this> */
    public function syncable(): MorphTo
    {
        return $this->morphTo();
    }
}
