<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_site_id
 * @property string $name
 * @property RecordStatus $status
 */
#[Fillable(['institution_id', 'clinical_site_id', 'name', 'status'])]
class Department extends Model
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

    /** @return HasMany<Ward, $this> */
    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class);
    }
}
