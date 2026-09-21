<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, User $subject): bool
    {
        if ($user->institution_id !== $subject->institution_id) {
            return false;
        }

        return $user->is($subject) || $user->role === UserRole::Administrator;
    }

    public function update(User $user, User $subject): bool
    {
        return $user->role === UserRole::Administrator
            && $subject->role !== UserRole::Administrator
            && $user->institution_id === $subject->institution_id;
    }
}
