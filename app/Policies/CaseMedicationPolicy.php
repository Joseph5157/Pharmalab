<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseMedication;
use App\Models\User;

class CaseMedicationPolicy
{
    public function view(User $user, CaseMedication $medication): bool
    {
        if ($medication->institution_id !== $user->institution_id) {
            return false;
        }

        $case = $medication->clinicalCase;

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

    public function update(User $user, CaseMedication $medication): bool
    {
        if (! $this->view($user, $medication)) {
            return false;
        }

        if ($user->role !== UserRole::Student) {
            return false;
        }

        return in_array($medication->clinicalCase?->status, [CaseStatus::Draft, CaseStatus::Returned]);
    }
}
