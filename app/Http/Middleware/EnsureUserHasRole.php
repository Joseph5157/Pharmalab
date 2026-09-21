<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role;
        $allowedRoles = array_map(
            static fn (string $value): UserRole => UserRole::from($value),
            $roles,
        );

        abort_unless($role !== null && in_array($role, $allowedRoles, true), 403);

        return $next($request);
    }
}
