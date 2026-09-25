<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseClinicalProfile;
use App\Models\User;

class CaseClinicalProfilePolicy
{
    public function view(User $user, CaseClinicalProfile $profile): bool
    {
        if ($profile->institution_id !== $user->institution_id) {
            return false;
        }

        $case = $profile->clinicalCase;

        if ($user->role === UserRole::Student) {
            return $case->student_id === $user->id;
        }

        if ($user->role === UserRole::Faculty) {
            return $case->rotationAssignment->primary_preceptor_id === $user->id;
        }

        return false;
    }

    public function update(User $user, CaseClinicalProfile $profile): bool
    {
        if (! $this->view($user, $profile)) {
            return false;
        }

        if ($user->role !== UserRole::Student) {
            return false;
        }

        return in_array($profile->clinicalCase->status, [CaseStatus::Draft, CaseStatus::Returned]);
    }
}
