<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PeopleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('admin/People', [
            'people' => User::query()
                ->whereIn('role', [UserRole::Student, UserRole::Faculty])
                ->orderBy('role')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'status', 'created_at']),
        ]);
    }

    public function store(Request $request, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)->only([UserRole::Student, UserRole::Faculty])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        DB::transaction(function () use ($request, $audit, $data): void {
            $person = User::query()->create([
                ...$data,
                'institution_id' => $request->user()->institution_id,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]);
            $audit->record($request->user(), $person, 'user.created', ['role' => $person->role->value]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Account created.']);
    }

    public function updateStatus(Request $request, User $user, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('update', $user);
        $data = $request->validate(['status' => ['required', Rule::enum(UserStatus::class)]]);
        $before = $user->status->value;

        DB::transaction(function () use ($request, $audit, $data, $user, $before): void {
            $user->update(['status' => $data['status']]);
            $audit->record($request->user(), $user, 'user.status_changed', [
                'from' => $before,
                'to' => $user->status->value,
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Account status updated.']);
    }
}
