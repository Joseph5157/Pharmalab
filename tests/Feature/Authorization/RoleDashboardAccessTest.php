<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roleAccessProvider')]
    public function test_each_role_can_only_open_its_own_dashboard(
        UserRole $role,
        string $allowedRoute,
        string $component,
        array $forbiddenRoutes,
    ): void {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route($allowedRoute))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));

        foreach ($forbiddenRoutes as $route) {
            $this->actingAs($user)->get(route($route))->assertForbidden();
        }
    }

    public function test_inactive_accounts_are_signed_out(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::Inactive,
        ]);

        $this->actingAs($user)
            ->get(route('student.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public static function roleAccessProvider(): array
    {
        return [
            'student' => [UserRole::Student, 'student.dashboard', 'student/Dashboard', ['faculty.dashboard', 'admin.dashboard']],
            'faculty' => [UserRole::Faculty, 'faculty.dashboard', 'faculty/Dashboard', ['student.dashboard', 'admin.dashboard']],
            'administrator' => [UserRole::Administrator, 'admin.dashboard', 'admin/Dashboard', ['student.dashboard', 'faculty.dashboard']],
        ];
    }
}
