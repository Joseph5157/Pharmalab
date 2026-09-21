<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CaseDraftNote;
use App\Models\User;

class CaseDraftNotePolicy
{
    public function view(User $user, CaseDraftNote $note): bool
    {
        return $user->role === UserRole::Student
            && $user->institution_id === $note->institution_id
            && $user->id === $note->student_id;
    }

    public function update(User $user, CaseDraftNote $note): bool
    {
        return $this->view($user, $note);
    }
}
