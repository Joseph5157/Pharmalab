<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A faculty-configured case target on one rotation. Every student assigned
 * to the rotation is measured against it individually.
 *
 * `category_key` is a database-generated column (lower(trim(category))) and
 * is deliberately not fillable: the model never writes it.
 *
 * @property string $id
 * @property string $institution_id
 * @property string $rotation_id
 * @property string|null $category
 * @property string|null $category_key
 * @property int $target_count
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['institution_id', 'rotation_id', 'category', 'target_count', 'created_by'])]
class RotationCaseTarget extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return ['target_count' => 'integer'];
    }

    /** @return BelongsTo<Rotation, $this> */
    public function rotation(): BelongsTo
    {
        return $this->belongsTo(Rotation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function normalize(?string $category): ?string
    {
        if ($category === null) {
            return null;
        }

        $key = mb_strtolower(trim($category));

        return $key === '' ? null : $key;
    }
}
