<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $name
 * @property string $code
 * @property int $duration_years
 * @property RecordStatus $status
 */
#[Fillable(['institution_id', 'name', 'code', 'duration_years', 'status'])]
class Programme extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return ['duration_years' => 'integer', 'status' => RecordStatus::class];
    }

    /** @return HasMany<AcademicCohort, $this> */
    public function cohorts(): HasMany
    {
        return $this->hasMany(AcademicCohort::class);
    }
}
