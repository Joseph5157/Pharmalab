<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\ClinicalCase;
use App\Models\User;

class ClinicalCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function create(User $user): bool
    {
        if ($user->role !== UserRole::Student) {
            return false;
        }

        return $user->rotationAssignments()
            ->where('status', 'active')
            ->exists();
    }

    public function view(User $user, ClinicalCase $case): bool
    {
        if ($case->institution_id !== $user->institution_id) {
            return false;
        }

        if ($user->role === UserRole::Student) {
            return $case->student_id === $user->id;
        }

        if ($user->role === UserRole::Faculty) {
            return $case->rotationAssignment->primary_preceptor_id === $user->id;
        }

        return false;
    }

    public function update(User $user, ClinicalCase $case): bool
    {
        if (! $this->view($user, $case)) {
            return false;
        }

        return $user->role === UserRole::Student
            && in_array($case->status, [CaseStatus::Draft, CaseStatus::Returned]);
    }

    public function submit(User $user, ClinicalCase $case): bool
    {
        if ($case->institution_id !== $user->institution_id) {
            return false;
        }

        return $user->role === UserRole::Student
            && $case->student_id === $user->id
            && in_array($case->status, [CaseStatus::Draft, CaseStatus::Returned]);
    }

    public function review(User $user, ClinicalCase $case): bool
    {
        if ($case->institution_id !== $user->institution_id) {
            return false;
        }

        return $user->role === UserRole::Faculty
            && $case->rotationAssignment->primary_preceptor_id === $user->id
            && in_array($case->status, [CaseStatus::Submitted, CaseStatus::UnderReview]);
    }

    public function approve(User $user, ClinicalCase $case): bool
    {
        return $this->review($user, $case);
    }

    public function returnCase(User $user, ClinicalCase $case): bool
    {
        return $this->review($user, $case);
    }
}
