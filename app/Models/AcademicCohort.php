<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $programme_id
 * @property string $name
 * @property int $admission_year
 * @property string $academic_year_label
 * @property RecordStatus $status
 */
#[Fillable(['institution_id', 'programme_id', 'name', 'admission_year', 'academic_year_label', 'status'])]
class AcademicCohort extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return ['admission_year' => 'integer', 'status' => RecordStatus::class];
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}
