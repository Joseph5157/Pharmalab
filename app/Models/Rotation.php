<?php

namespace App\Models;

use App\Enums\RotationStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $programme_id
 * @property string|null $academic_cohort_id
 * @property string $clinical_site_id
 * @property string|null $department_id
 * @property string|null $ward_id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property RotationStatus $status
 */
#[Fillable(['institution_id', 'programme_id', 'academic_cohort_id', 'clinical_site_id', 'department_id', 'ward_id', 'name', 'starts_on', 'ends_on', 'status'])]
class Rotation extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'status' => RotationStatus::class];
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /** @return BelongsTo<AcademicCohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(AcademicCohort::class, 'academic_cohort_id');
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

    /** @return HasMany<RotationAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(RotationAssignment::class);
    }
}
