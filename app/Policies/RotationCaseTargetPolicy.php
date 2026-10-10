<?php

namespace App\Policies;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\Rotation;
use App\Models\User;

/**
 * Targets belong to a rotation, so every ability takes the Rotation.
 *
 * Laravel resolves a policy from the class of the first model argument, so
 * `Gate::authorize('manage', $rotation)` would reach RotationPolicy (which is
 * administrator-only) and never this class. Always pass the target class
 * explicitly: `Gate::authorize('manage', [RotationCaseTarget::class, $rotation])`.
 */
class RotationCaseTargetPolicy
{
    /**
     * Any faculty member currently assigned as primary preceptor on this
     * rotation may create, edit or delete any of its targets, whoever
     * created them.
     */
    public function manage(User $user, Rotation $rotation): bool
    {
        if ($rotation->institution_id !== $user->institution_id || $user->role !== UserRole::Faculty) {
            return false;
        }

        return $rotation->assignments()
            ->where('primary_preceptor_id', $user->id)
            ->where('status', RecordStatus::Active)
            ->exists();
    }

    /** Managing faculty, or a same-institution administrator (read-only). */
    public function view(User $user, Rotation $rotation): bool
    {
        if ($user->role === UserRole::Administrator) {
            return $rotation->institution_id === $user->institution_id;
        }

        return $this->manage($user, $rotation);
    }
}
