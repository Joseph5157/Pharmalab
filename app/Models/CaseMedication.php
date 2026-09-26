<?php

namespace App\Models;

use App\Contracts\Syncable;
use App\Enums\MedicationStatus;
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
 * @property string $medication_context
 * @property string|null $generic_name
 * @property string|null $brand_name
 * @property string|null $indication
 * @property bool $indication_unclear
 * @property string|null $dose_amount
 * @property string|null $dose_unit
 * @property string|null $dosage_form
 * @property string|null $route
 * @property string|null $frequency
 * @property string|null $start_reference
 * @property string|null $stop_reference
 * @property MedicationStatus|null $status
 * @property string|null $prn_indication
 * @property string|null $notes
 * @property int $recorded_by
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'medication_context',
    'generic_name',
    'brand_name',
    'indication',
    'indication_unclear',
    'dose_amount',
    'dose_unit',
    'dosage_form',
    'route',
    'frequency',
    'start_reference',
    'stop_reference',
    'status',
    'prn_indication',
    'notes',
    'recorded_by',
])]
class CaseMedication extends Model implements Syncable
{
    use BelongsToInstitution, HasUlids, SyncsWithLockVersion;

    protected function casts(): array
    {
        return [
            'status' => MedicationStatus::class,
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
