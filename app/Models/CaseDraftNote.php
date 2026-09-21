<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $case_id
 * @property int $student_id
 * @property string $institution_id
 * @property string $content
 * @property int $lock_version
 * @property Carbon $updated_at
 */
class CaseDraftNote extends Model
{
    use BelongsToInstitution, HasUlids;

    protected $fillable = ['case_id', 'student_id', 'institution_id', 'content', 'lock_version'];

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
