<?php

namespace Tests\Feature;

use App\Demo\DemoActingAs;
use App\Demo\DemoGuard;
use App\Demo\DemoRefusedException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Tests\Support\EnablesDemoMode;
use Tests\TestCase;

class DemoGuardTest extends TestCase
{
    use EnablesDemoMode, RefreshDatabase;

    public function test_demo_mode_is_off_by_default(): void
    {
        $this->assertFalse(config('demo.enabled'));
    }

    public function test_the_guard_refuses_when_demo_mode_is_disabled(): void
    {
        config(['demo.enabled' => false, 'demo.password' => self::DEMO_TEST_PASSWORD]);

        $this->expectException(DemoRefusedException::class);
        $this->expectExceptionMessage('DEMO_MODE is not enabled');

        app(DemoGuard::class)->password();
    }

    public function test_the_guard_refuses_a_missing_password(): void
    {
        config(['demo.enabled' => true, 'demo.password' => null]);

        $this->expectException(DemoRefusedException::class);
        $this->expectExceptionMessage('DEMO_PASSWORD must be set to at least 12 characters.');

        app(DemoGuard::class)->password();
    }

    public function test_the_guard_refuses_a_short_password_without_revealing_it(): void
    {
        $this->enableDemoMode('short-pass1');

        try {
            app(DemoGuard::class)->password();
            $this->fail('Expected a refusal.');
        } catch (DemoRefusedException $e) {
            $this->assertStringNotContainsString('short-pass1', $e->getMessage());
        }
    }

    public function test_the_guard_returns_the_password_in_production_environments(): void
    {
        $this->app['env'] = 'production';
        $this->enableDemoMode();

        $this->assertSame(self::DEMO_TEST_PASSWORD, app(DemoGuard::class)->password());
    }

    public function test_acting_as_sets_the_user_only_for_the_step(): void
    {
        $user = User::factory()->create();

        $seen = app(DemoActingAs::class)->run($user, fn (): ?int => Auth::id());

        $this->assertSame($user->id, $seen);
        $this->assertFalse(Auth::guard()->hasUser());
    }

    public function test_acting_as_restores_the_previous_user_even_when_the_step_throws(): void
    {
        $previous = User::factory()->create();
        $demo = User::factory()->create();
        Auth::setUser($previous);

        try {
            app(DemoActingAs::class)->run($demo, function (): never {
                throw new RuntimeException('step failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame($previous->id, Auth::id());
    }
}
