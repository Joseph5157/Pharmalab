<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseClinicalActivity;
use App\Models\User;

class CaseClinicalActivityPolicy
{
    public function view(User $user, CaseClinicalActivity $activity): bool
    {
        if ($activity->institution_id !== $user->institution_id) {
            return false;
        }

        $case = $activity->clinicalCase;

        if ($user->role === UserRole::Student) {
            return $case->student_id === $user->id;
        }

        if ($user->role === UserRole::Faculty) {
            return $case->rotationAssignment->primary_preceptor_id === $user->id;
        }

        return false;
    }

    public function update(User $user, CaseClinicalActivity $activity): bool
    {
        if (! $this->view($user, $activity)) {
            return false;
        }

        if ($user->role !== UserRole::Student) {
            return false;
        }

        return in_array($activity->clinicalCase->status, [CaseStatus::Draft, CaseStatus::Returned]);
    }
}
