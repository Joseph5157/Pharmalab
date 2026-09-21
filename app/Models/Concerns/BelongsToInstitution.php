<?php

namespace App\Models\Concerns;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToInstitution
{
    public static function bootBelongsToInstitution(): void
    {
        static::addGlobalScope('institution', function (Builder $builder): void {
            if (! Auth::guard()->hasUser()) {
                return;
            }

            $institutionId = Auth::user()?->institution_id;

            if ($institutionId !== null) {
                $builder->where(
                    $builder->qualifyColumn('institution_id'),
                    $institutionId,
                );
            }
        });

        static::creating(function ($model): void {
            if (Auth::guard()->hasUser() && $model->institution_id === null && Auth::user()?->institution_id !== null) {
                $model->institution_id = Auth::user()->institution_id;
            }
        });
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
