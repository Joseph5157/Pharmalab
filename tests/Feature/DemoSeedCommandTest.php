<?php

namespace Tests\Feature;

use App\Demo\DemoDataset;
use App\Enums\UserRole;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\RotationCaseTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\EnablesDemoMode;
use Tests\TestCase;

class DemoSeedCommandTest extends TestCase
{
    use EnablesDemoMode, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableDemoMode();
    }

    public function test_seed_refuses_when_demo_mode_is_disabled_and_writes_nothing(): void
    {
        config(['demo.enabled' => false]);

        $this->artisan('demo:seed')
            ->expectsOutputToContain('Demo seed refused: DEMO_MODE is not enabled')
            ->assertFailed();

        $this->assertSame(0, Institution::query()->count());
    }

    public function test_seed_refuses_a_short_password_and_writes_nothing(): void
    {
        $this->enableDemoMode('short-pass1');

        $this->artisan('demo:seed')
            ->expectsOutputToContain('DEMO_PASSWORD must be set to at least 12 characters.')
            ->assertFailed();

        $this->assertSame(0, Institution::query()->count());
    }

    public function test_seed_runs_when_app_env_is_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('demo:seed')->assertSuccessful();

        $this->assertSame(1, Institution::query()->where('slug', DemoDataset::INSTITUTION_SLUG)->count());
    }

    public function test_seed_creates_the_institution_structure_accounts_assignments_and_targets(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $institution = Institution::query()->where('slug', DemoDataset::INSTITUTION_SLUG)->sole();
        $this->assertSame(DemoDataset::INSTITUTION_NAME, $institution->name);

        $users = User::query()->withoutGlobalScopes()->where('institution_id', $institution->id)->orderBy('email')->get();
        $this->assertEqualsCanonicalizing(array_keys(DemoDataset::users()), $users->pluck('email')->all());
        foreach ($users as $user) {
            $this->assertSame(DemoDataset::users()[$user->email]['role'], $user->role);
            $this->assertNotNull($user->email_verified_at);
            $this->assertTrue(Hash::check(self::DEMO_TEST_PASSWORD, $user->password));
        }

        $rotation = Rotation::query()->withoutGlobalScopes()->where('institution_id', $institution->id)->sole();
        $this->assertSame(DemoDataset::ROTATION_NAME, $rotation->name);
        $this->assertSame(DemoDataset::ROTATION_STARTS_ON, $rotation->starts_on->toDateString());
        $this->assertSame(DemoDataset::ROTATION_ENDS_ON, $rotation->ends_on->toDateString());
        $this->assertSame(DemoDataset::SITE_NAME, ClinicalSite::query()->withoutGlobalScopes()->findOrFail($rotation->clinical_site_id)->name);

        $faculty = $users->firstWhere('email', DemoDataset::FACULTY);
        $assignments = RotationAssignment::query()->withoutGlobalScopes()->where('rotation_id', $rotation->id)->get();
        $this->assertCount(2, $assignments);
        $this->assertTrue($assignments->every(fn (RotationAssignment $a): bool => $a->primary_preceptor_id === $faculty->id));

        $targets = RotationCaseTarget::query()->withoutGlobalScopes()->where('rotation_id', $rotation->id)->get()
            ->map(fn (RotationCaseTarget $t): array => ['category' => $t->category, 'target_count' => $t->target_count])
            ->sortBy(fn (array $t): string => (string) $t['category'])->values()->all();
        $this->assertSame([
            ['category' => null, 'target_count' => 6],
            ['category' => 'Cardiology', 'target_count' => 2],
            ['category' => 'Infectious Disease', 'target_count' => 2],
            ['category' => 'Respiratory', 'target_count' => 2],
        ], $targets);
    }

    public function test_running_seed_twice_changes_no_row_counts(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $before = $this->tableCounts();

        $this->artisan('demo:seed')->assertSuccessful();

        $this->assertSame($before, $this->tableCounts());
    }

    public function test_rerunning_seed_rotates_all_four_passwords(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $this->enableDemoMode('a-brand-new-demo-password');

        $this->artisan('demo:seed')->assertSuccessful();

        foreach (array_keys(DemoDataset::users()) as $email) {
            $user = User::query()->withoutGlobalScopes()->where('email', $email)->sole();
            $this->assertTrue(Hash::check('a-brand-new-demo-password', $user->password), $email);
        }
    }

    public function test_no_user_is_authenticated_after_seed(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $this->assertFalse(Auth::guard()->hasUser());
    }

    public function test_seed_refuses_when_a_demo_email_belongs_to_another_institution(): void
    {
        $other = Institution::factory()->create();
        User::factory()->create(['email' => DemoDataset::STUDENT1, 'institution_id' => $other->id, 'role' => UserRole::Student]);

        $this->artisan('demo:seed')
            ->expectsOutputToContain('Demo seed refused: user student1@pharmalab-demo.test belongs to another institution. No demo data was changed.')
            ->assertFailed();

        $this->assertSame(0, Institution::query()->where('slug', DemoDataset::INSTITUTION_SLUG)->count());
    }

    public function test_seed_refuses_when_the_institution_slug_has_a_different_name(): void
    {
        Institution::query()->create(['slug' => DemoDataset::INSTITUTION_SLUG, 'name' => 'Real College', 'status' => 'active', 'timezone' => 'Asia/Kolkata']);

        $this->artisan('demo:seed')
            ->expectsOutputToContain('Demo seed refused: institution pharmalab-demo-college exists with a different name.')
            ->assertFailed();

        $this->assertSame(0, User::query()->withoutGlobalScopes()->count());
    }

    public function test_seed_refuses_when_the_demo_rotation_points_at_another_site(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $institution = Institution::query()->where('slug', DemoDataset::INSTITUTION_SLUG)->sole();
        $elsewhere = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Elsewhere', 'code' => 'ELS', 'status' => 'active']);
        Rotation::query()->withoutGlobalScopes()->where('institution_id', $institution->id)->update(['clinical_site_id' => $elsewhere->id]);
        $before = $this->tableCounts();

        $this->artisan('demo:seed')
            ->expectsOutputToContain('Demo seed refused: rotation General Medicine Clerkship exists but its clinical_site_id does not match the demo dataset. No demo data was changed.')
            ->assertFailed();

        $this->assertSame($before, $this->tableCounts());
    }

    public function test_a_changed_target_count_makes_seed_refuse(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        RotationCaseTarget::query()->withoutGlobalScopes()->where('category', 'Cardiology')->update(['target_count' => 5]);

        $this->artisan('demo:seed')
            ->expectsOutputToContain('Demo seed refused: rotation case target Cardiology exists but its target_count does not match the demo dataset. No demo data was changed.')
            ->assertFailed();
    }

    public function test_the_password_never_appears_in_seed_output(): void
    {
        $this->enableDemoMode('Sup3r-Secret-Demo-Pass');

        Artisan::call('demo:seed');

        $this->assertStringNotContainsString('Sup3r-Secret-Demo-Pass', Artisan::output());
    }

    /** @return array<string, int> */
    private function tableCounts(): array
    {
        $counts = [];
        foreach (['institutions', 'users', 'programmes', 'academic_cohorts', 'clinical_sites', 'departments', 'wards', 'rotations', 'rotation_assignments', 'rotation_case_targets', 'clinical_cases', 'case_versions', 'case_status_transitions', 'case_review_comments', 'audit_events'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
