<?php

namespace App\Policies;

use App\Models\Institution;
use App\Models\User;

class InstitutionPolicy
{
    public function view(User $user, Institution $institution): bool
    {
        return $user->institution_id === $institution->id;
    }
}
