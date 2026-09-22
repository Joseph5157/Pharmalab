<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_case_id
 * @property string $from_status
 * @property string $to_status
 * @property int $actor_id
 * @property string|null $case_version_id
 * @property string|null $reason
 * @property Carbon $created_at
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'from_status',
    'to_status',
    'actor_id',
    'case_version_id',
    'reason',
])]
class CaseStatusTransition extends Model
{
    use BelongsToInstitution, HasUlids;

    public $timestamps = false;

    /** @return BelongsTo<ClinicalCase, $this> */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<CaseVersion, $this> */
    public function caseVersion(): BelongsTo
    {
        return $this->belongsTo(CaseVersion::class);
    }
}
