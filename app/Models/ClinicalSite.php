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
 * @property RecordStatus $status
 */
#[Fillable(['institution_id', 'name', 'code', 'status'])]
class ClinicalSite extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return ['status' => RecordStatus::class];
    }

    /** @return HasMany<Department, $this> */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /** @return HasMany<Ward, $this> */
    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class);
    }
}
