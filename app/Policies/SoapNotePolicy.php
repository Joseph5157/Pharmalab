<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ClinicalCase;
use App\Models\SoapNote;
use App\Models\User;

class SoapNotePolicy
{
    public function view(User $user, SoapNote $soapNote): bool
    {
        if ($soapNote->institution_id !== $user->institution_id) {
            return false;
        }

        $case = $soapNote->clinicalCase;

        if ($user->role === UserRole::Student) {
            return $case->student_id === $user->id;
        }

        if ($user->role === UserRole::Faculty) {
            return $case->rotationAssignment->primary_preceptor_id === $user->id;
        }

        return false;
    }

    public function update(User $user, SoapNote $soapNote): bool
    {
        if (! $this->view($user, $soapNote)) {
            return false;
        }

        if ($user->role !== UserRole::Student) {
            return false;
        }

        $case = $soapNote->clinicalCase;

        return in_array($case->status, [CaseStatus::Draft, CaseStatus::Returned])
            && $soapNote->clinical_case_id === $case->id;
    }
}
