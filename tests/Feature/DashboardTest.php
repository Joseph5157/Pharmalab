<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    #[DataProvider('roleDashboardProvider')]
    public function test_authenticated_users_are_redirected_to_their_role_dashboard(
        UserRole $role,
        string $route,
    ): void {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route($route));
    }

    public static function roleDashboardProvider(): array
    {
        return [
            'student' => [UserRole::Student, 'student.dashboard'],
            'faculty' => [UserRole::Faculty, 'faculty.dashboard'],
            'administrator' => [UserRole::Administrator, 'admin.dashboard'],
        ];
    }
}
