<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CaseVersion;
use App\Models\User;

class CaseVersionPolicy
{
    public function view(User $user, CaseVersion $version): bool
    {
        if ($version->institution_id !== $user->institution_id) {
            return false;
        }

        $case = $version->clinicalCase;

        if ($user->role === UserRole::Student) {
            return $case->student_id === $user->id;
        }

        if ($user->role === UserRole::Faculty) {
            return $case->rotationAssignment->primary_preceptor_id === $user->id;
        }

        return false;
    }
}
