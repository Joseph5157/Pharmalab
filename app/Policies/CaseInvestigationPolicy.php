<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseInvestigation;
use App\Models\User;

class CaseInvestigationPolicy
{
    public function view(User $user, CaseInvestigation $investigation): bool
    {
        if ($investigation->institution_id !== $user->institution_id) {
            return false;
        }

        $case = $investigation->clinicalCase;

        if ($user->role === UserRole::Student) {
            return $case->student_id === $user->id;
        }

        if ($user->role === UserRole::Faculty) {
            return $case->rotationAssignment->primary_preceptor_id === $user->id;
        }

        return false;
    }

    public function update(User $user, CaseInvestigation $investigation): bool
    {
        if (! $this->view($user, $investigation)) {
            return false;
        }

        if ($user->role !== UserRole::Student) {
            return false;
        }

        return in_array($investigation->clinicalCase->status, [CaseStatus::Draft, CaseStatus::Returned]);
    }
}
