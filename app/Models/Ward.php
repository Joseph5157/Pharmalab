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
 * @property string $clinical_site_id
 * @property string|null $department_id
 * @property string $name
 * @property string $code
 * @property RecordStatus $status
 */
#[Fillable(['institution_id', 'clinical_site_id', 'department_id', 'name', 'code', 'status'])]
class Ward extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return ['status' => RecordStatus::class];
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
}
