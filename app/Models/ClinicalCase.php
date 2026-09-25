<?php

namespace App\Models;

use App\Enums\CaseFormVersion;
use App\Enums\CaseStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $rotation_assignment_id
 * @property int $student_id
 * @property int $case_number
 * @property CaseStatus $status
 * @property int $current_revision_number
 * @property int $lock_version
 * @property string|null $encounter_date
 * @property string|null $case_category
 * @property int|null $age_value
 * @property string|null $age_unit
 * @property string|null $sex
 * @property string|null $clinical_site_id
 * @property string|null $department_id
 * @property string|null $ward_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'institution_id',
    'rotation_assignment_id',
    'student_id',
    'case_number',
    'status',
    'current_revision_number',
    'form_version',
    'encounter_date',
    'case_category',
    'age_value',
    'age_unit',
    'sex',
    'care_setting',
    'hospital_day_at_first_review',
    'information_source',
    'weight_kg',
    'height_cm',
    'pregnancy_lactation_status',
    'deidentification_attested_at',
    'deidentification_attested_by',
    'clinical_site_id',
    'department_id',
    'ward_id',
    'submitted_at',
    'approved_at',
])]
class ClinicalCase extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'form_version' => CaseFormVersion::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'encounter_date' => 'date',
            'deidentification_attested_at' => 'datetime',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<RotationAssignment, $this> */
    public function rotationAssignment(): BelongsTo
    {
        return $this->belongsTo(RotationAssignment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<ClinicalSite, $this> */
    public function clinicalSite(): BelongsTo
    {
        return $this->belongsTo(ClinicalSite::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Ward, $this> */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /** @return BelongsTo<User, $this> */
    public function attestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deidentification_attested_by');
    }

    /** @return HasOne<CaseClinicalProfile, $this> */
    public function clinicalProfile(): HasOne
    {
        return $this->hasOne(CaseClinicalProfile::class);
    }

    /** @return HasMany<CaseVital, $this> */
    public function vitals(): HasMany
    {
        return $this->hasMany(CaseVital::class);
    }

    /** @return HasMany<CaseInvestigation, $this> */
    public function investigations(): HasMany
    {
        return $this->hasMany(CaseInvestigation::class);
    }

    /** @return HasOne<SoapNote, $this> */
    public function currentSoap(): HasOne
    {
        return $this->hasOne(SoapNote::class)->latestOfMany('revision_number');
    }

    /** @return HasMany<SoapNote, $this> */
    public function soapNotes(): HasMany
    {
        return $this->hasMany(SoapNote::class);
    }

    /** @return HasMany<CaseVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(CaseVersion::class);
    }

    /** @return HasMany<CaseStatusTransition, $this> */
    public function statusTransitions(): HasMany
    {
        return $this->hasMany(CaseStatusTransition::class);
    }
}
