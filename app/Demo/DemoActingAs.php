<?php

namespace App\Demo;

use App\Models\User;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;

/**
 * An Artisan command starts with no authenticated user, so institution global
 * scopes are inactive. Each workflow step sets exactly one demo user and
 * restores whatever was there before, even when the step throws, so no
 * identity leaks between steps or scenarios.
 */
class DemoActingAs
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $step
     * @return TResult
     */
    public function run(User $user, Closure $step): mixed
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard();
        $previous = $guard->hasUser() ? $guard->user() : null;

        try {
            $guard->setUser($user);

            return $step();
        } finally {
            if ($previous === null) {
                $guard->forgetUser();
            } else {
                $guard->setUser($previous);
            }
        }
    }
}
