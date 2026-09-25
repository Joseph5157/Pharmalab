<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseVital;
use App\Models\User;

class CaseVitalPolicy
{
    public function view(User $user, CaseVital $vital): bool
    {
        if ($vital->institution_id !== $user->institution_id) {
            return false;
        }

        $case = $vital->clinicalCase;

        if ($case === null) {
            return false;
        }

        if ($user->role === UserRole::Student) {
            return $case->student_id === $user->id;
        }

        if ($user->role === UserRole::Administrator) {
            return true;
        }

        return $case->rotationAssignment?->primary_preceptor_id === $user->id;
    }

    public function update(User $user, CaseVital $vital): bool
    {
        if (! $this->view($user, $vital)) {
            return false;
        }

        if ($user->role !== UserRole::Student) {
            return false;
        }

        return in_array($vital->clinicalCase?->status, [CaseStatus::Draft, CaseStatus::Returned]);
    }
}
