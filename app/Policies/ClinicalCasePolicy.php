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

    /**
     * Allows Submitted in addition to Draft/Returned so a duplicated or
     * retried POST to /submit while the case is already Submitted reaches
     * SubmitCase, which returns the existing version idempotently instead of
     * erroring or being denied. UnderReview/Approved are intentionally
     * excluded: once a faculty member has started or finished reviewing,
     * submission is a one-way door and a POST at that point is a genuine
     * denial, not a no-op.
     */
    public function submit(User $user, ClinicalCase $case): bool
    {
        if ($case->institution_id !== $user->institution_id) {
            return false;
        }

        return $user->role === UserRole::Student
            && $case->student_id === $user->id
            && in_array($case->status, [CaseStatus::Draft, CaseStatus::Returned, CaseStatus::Submitted], true);
    }

    /**
     * Narrower than submit(): the read-only submission-review screen only
     * makes sense while there is still something to review before
     * submitting. Once a case is Submitted (or later), there is nothing left
     * to review-before-submitting, so this denies where submit() now allows.
     */
    public function reviewForSubmission(User $user, ClinicalCase $case): bool
    {
        if ($case->institution_id !== $user->institution_id) {
            return false;
        }

        return $user->role === UserRole::Student
            && $case->student_id === $user->id
            && in_array($case->status, [CaseStatus::Draft, CaseStatus::Returned], true);
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

    public function reopen(User $user, ClinicalCase $case): bool
    {
        if ($case->institution_id !== $user->institution_id) {
            return false;
        }

        return $user->role === UserRole::Faculty
            && $case->rotationAssignment->primary_preceptor_id === $user->id
            && $case->status === CaseStatus::Approved;
    }
}
