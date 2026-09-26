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
 * @property string $test_name
 * @property string $result_type
 * @property string $result_value
 * @property string|null $unit
 * @property bool $unit_not_stated
 * @property string|null $reference_range
 * @property bool $reference_range_not_provided
 * @property string|null $reported_flag
 * @property string|null $observed_on
 * @property string|null $observed_at_time
 * @property string|null $interpretation
 * @property int $recorded_by
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'test_name',
    'result_type',
    'result_value',
    'unit',
    'unit_not_stated',
    'reference_range',
    'reference_range_not_provided',
    'reported_flag',
    'observed_on',
    'observed_at_time',
    'interpretation',
    'recorded_by',
])]
class CaseInvestigation extends Model implements Syncable
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
