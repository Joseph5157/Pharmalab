<?php

namespace App\Policies\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesInstitutionAdministrator
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->manages($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->manages($user, $record);
    }

    private function manages(User $user, Model $record): bool
    {
        return $user->role === UserRole::Administrator
            && $record->getAttribute('institution_id') === $user->institution_id;
    }
}
