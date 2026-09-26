<?php

namespace App\Models;

use App\Contracts\Syncable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\SyncsWithLockVersion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_case_id
 * @property string $observation_type
 * @property string|null $value_numeric
 * @property string|null $value_text
 * @property int|null $value_systolic
 * @property int|null $value_diastolic
 * @property string|null $unit
 * @property string|null $observed_on
 * @property string|null $observed_at_time
 * @property string|null $source
 * @property string|null $note
 * @property int $recorded_by
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'observation_type',
    'value_numeric',
    'value_text',
    'value_systolic',
    'value_diastolic',
    'unit',
    'observed_on',
    'observed_at_time',
    'source',
    'note',
    'recorded_by',
])]
class CaseVital extends Model implements Syncable
{
    use BelongsToInstitution, HasUlids, SyncsWithLockVersion;

    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
        ];
    }

    /** @return BelongsTo<ClinicalCase, $this> */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
