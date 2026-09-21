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
 * @property string $rotation_id
 * @property int $student_id
 * @property int $primary_preceptor_id
 * @property RecordStatus $status
 */
#[Fillable(['institution_id', 'rotation_id', 'student_id', 'primary_preceptor_id', 'status'])]
class RotationAssignment extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return ['status' => RecordStatus::class];
    }

    /** @return BelongsTo<Rotation, $this> */
    public function rotation(): BelongsTo
    {
        return $this->belongsTo(Rotation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_preceptor_id');
    }
}
