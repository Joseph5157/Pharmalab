<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function view(User $user, User $subject): bool
    {
        if ($user->institution_id !== $subject->institution_id) {
            return false;
        }

        return $user->is($subject) || $user->role === UserRole::Administrator;
    }
}
