<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_case_id
 * @property int $version_number
 * @property int $source_revision_number
 * @property array $snapshot
 * @property string $snapshot_hash
 * @property int $submitted_by
 * @property \Illuminate\Support\Carbon $submitted_at
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'version_number',
    'source_revision_number',
    'snapshot',
    'snapshot_hash',
    'submitted_by',
    'submitted_at',
    'approved_by',
    'approved_at',
])]
class CaseVersion extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ClinicalCase, $this> */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
