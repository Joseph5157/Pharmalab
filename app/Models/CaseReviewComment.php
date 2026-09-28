<?php

namespace App\Models;

use App\Enums\CaseReviewSection;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_case_id
 * @property string $case_status_transition_id
 * @property CaseReviewSection $section
 * @property string $body
 * @property bool $is_flagged
 * @property int $created_by
 * @property Carbon $created_at
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'case_status_transition_id',
    'section',
    'body',
    'is_flagged',
    'created_by',
])]
class CaseReviewComment extends Model
{
    use BelongsToInstitution, HasUlids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'section' => CaseReviewSection::class,
            'is_flagged' => 'boolean',
        ];
    }

    /** @return BelongsTo<ClinicalCase, $this> */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    /** @return BelongsTo<CaseStatusTransition, $this> */
    public function caseStatusTransition(): BelongsTo
    {
        return $this->belongsTo(CaseStatusTransition::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
