<?php

namespace App\Models;

use App\Contracts\Syncable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\SyncsWithLockVersion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_case_id
 * @property array<int, array<string, mixed>>|null $chief_complaints
 * @property string|null $history_present_illness
 * @property array<int, array<string, mixed>>|null $diagnoses
 * @property string|null $past_medical_history
 * @property bool $past_medical_history_none
 * @property string|null $past_surgical_history
 * @property string|null $adherence_status
 * @property string|null $family_history
 * @property string|null $substance_history
 * @property string|null $examination_findings
 * @property string $allergy_status
 * @property string|null $allergy_substance
 * @property string|null $allergy_reaction
 * @property int $last_saved_by
 * @property int $lock_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'chief_complaints',
    'history_present_illness',
    'diagnoses',
    'past_medical_history',
    'past_medical_history_none',
    'past_surgical_history',
    'adherence_status',
    'family_history',
    'substance_history',
    'examination_findings',
    'allergy_status',
    'allergy_substance',
    'allergy_reaction',
    'last_saved_by',
])]
class CaseClinicalProfile extends Model implements Syncable
{
    use BelongsToInstitution, HasUlids, SyncsWithLockVersion;

    protected function casts(): array
    {
        return [
            'chief_complaints' => 'array',
            'diagnoses' => 'array',
            'past_medical_history_none' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<ClinicalCase, $this> */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function lastSavedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_saved_by');
    }
}
