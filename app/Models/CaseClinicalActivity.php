<?php

namespace App\Models;

use App\Enums\ClinicalActivityType;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_case_id
 * @property ClinicalActivityType $activity_type
 * @property string|null $status
 * @property array<string, mixed>|null $details
 * @property int $recorded_by
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'activity_type',
    'status',
    'details',
    'recorded_by',
])]
class CaseClinicalActivity extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return [
            'activity_type' => ClinicalActivityType::class,
            'details' => 'array',
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
