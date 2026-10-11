<?php

namespace Tests\Feature;

use App\Console\Commands\DemoResetCommand;
use App\Demo\DemoCaseBuilder;
use App\Demo\DemoDataset;
use App\Demo\DemoSeeder;
use App\Enums\CaseStatus;
use App\Models\AuditEvent;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\EnablesDemoMode;
use Tests\TestCase;

class DemoResetCommandTest extends TestCase
{
    use EnablesDemoMode, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableDemoMode();
    }

    public function test_reset_refuses_when_demo_mode_is_disabled(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        config(['demo.enabled' => false]);
        $before = $this->counts();

        $this->artisan('demo:reset --force')
            ->expectsOutputToContain('Demo reset refused: DEMO_MODE is not enabled')
            ->assertFailed();

        $this->assertSame($before, $this->counts());
    }

    public function test_reset_without_an_existing_demo_simply_seeds(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertSame(8, ClinicalCase::query()->withoutGlobalScopes()->count());
    }

    public function test_reset_restores_cases_modified_or_added_during_a_demo(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $expected = $this->counts();
        $draft = $this->caseFor(DemoDataset::STUDENT1, 6);
        $draft->forceFill(['case_category' => 'Edited live'])->save();
        ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $draft->institution_id, 'student_id' => $draft->student_id,
            'rotation_assignment_id' => $draft->rotation_assignment_id, 'case_number' => 7, 'status' => CaseStatus::Draft,
        ]);

        $this->artisan('demo:reset --force')->assertSuccessful();

        $this->assertSame($expected, $this->counts());
        $this->assertNull($this->caseFor(DemoDataset::STUDENT1, 6)->case_category);
    }

    public function test_reset_leaves_other_institutions_untouched(): void
    {
        $other = Institution::factory()->create();
        $otherUser = User::factory()->create(['institution_id' => $other->id]);
        $otherSite = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $other->id, 'name' => 'Real Hospital', 'code' => 'RH', 'status' => 'active']);
        $this->artisan('demo:seed')->assertSuccessful();
        $snapshot = fn (): array => [
            DB::table('institutions')->where('id', $other->id)->get()->map(fn ($row): array => (array) $row)->all(),
            DB::table('users')->where('id', $otherUser->id)->get()->map(fn ($row): array => (array) $row)->all(),
            DB::table('clinical_sites')->where('id', $otherSite->id)->get()->map(fn ($row): array => (array) $row)->all(),
        ];
        $before = $snapshot();

        $this->artisan('demo:reset --force')->assertSuccessful();

        $this->assertCount(1, $before[0]);
        $this->assertSame($before, $snapshot());
    }

    public function test_reset_refuses_a_non_demo_user_even_with_force(): void
    {
        $institution = $this->seededInstitution();
        User::factory()->create(['institution_id' => $institution->id, 'email' => 'intruder@example.com']);
        $before = $this->counts();

        $this->artisan('demo:reset --force')
            ->expectsOutputToContain('Demo reset refused: the demo institution has users outside the demo allowlist: intruder@example.com. No data was changed.')
            ->assertFailed();

        $this->assertSame($before, $this->counts());
    }

    public function test_reset_refuses_an_extra_demo_domain_user_even_with_force(): void
    {
        $institution = $this->seededInstitution();
        User::factory()->create(['institution_id' => $institution->id, 'email' => 'extra@pharmalab-demo.test']);

        $this->artisan('demo:reset --force')
            ->expectsOutputToContain('extra@pharmalab-demo.test')
            ->assertFailed();
    }

    public function test_reset_refuses_an_allowlisted_account_in_another_institution_even_with_force(): void
    {
        $this->seededInstitution();
        $other = Institution::factory()->create();
        User::query()->withoutGlobalScopes()->where('email', DemoDataset::STUDENT2)->update(['institution_id' => $other->id]);

        $this->artisan('demo:reset --force')
            ->expectsOutputToContain('Demo reset refused: demo accounts are linked to another institution: student2@pharmalab-demo.test. No data was changed.')
            ->assertFailed();
    }

    public function test_answering_no_shows_the_summary_and_writes_nothing(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $before = $this->counts();
        $hashes = $this->passwordHashes();

        $this->artisan('demo:reset')
            ->expectsOutputToContain('Pharmalab Demo College (pharmalab-demo-college)')
            ->expectsConfirmation(DemoResetCommand::CONFIRMATION, 'no')
            ->expectsOutputToContain('Demo reset cancelled. No data was changed.')
            ->assertSuccessful();

        $this->assertSame($before, $this->counts());
        $this->assertSame($hashes, $this->passwordHashes());
    }

    public function test_answering_yes_rebuilds_the_demo(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $firstInstitutionId = $this->seededInstitution()->id;

        $this->artisan('demo:reset')
            ->expectsConfirmation(DemoResetCommand::CONFIRMATION, 'yes')
            ->assertSuccessful();

        $this->assertNotSame($firstInstitutionId, $this->seededInstitution()->id);
        $this->assertSame(8, ClinicalCase::query()->withoutGlobalScopes()->count());
    }

    public function test_a_rebuild_failure_restores_the_original_demo_exactly(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $student = User::query()->withoutGlobalScopes()->where('email', DemoDataset::STUDENT1)->sole();
        DB::table('sessions')->insert(['id' => 'live-demo-session', 'user_id' => $student->id, 'payload' => '', 'last_activity' => time()]);
        $before = $this->counts();
        $hashes = $this->passwordHashes();
        $caseIds = ClinicalCase::query()->withoutGlobalScopes()->orderBy('id')->pluck('id')->all();
        $this->enableDemoMode('a-different-demo-password');
        // Fails only after the purge AND a complete rebuild with a rotated
        // password, so every write of the reset must be rolled back.
        $this->app->instance(DemoSeeder::class, new class(app(DemoCaseBuilder::class)) extends DemoSeeder
        {
            public function seed(string $password): void
            {
                parent::seed($password);

                throw new RuntimeException('simulated rebuild failure');
            }
        });

        Artisan::call('demo:reset', ['--force' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('Demo reset failed while rebuilding the demo dataset (finished). No data was changed.', $output);
        $this->assertStringNotContainsString('simulated rebuild failure', $output);
        $this->assertSame($before, $this->counts());
        $this->assertSame($hashes, $this->passwordHashes());
        $this->assertSame($caseIds, ClinicalCase::query()->withoutGlobalScopes()->orderBy('id')->pluck('id')->all());
        $this->assertSame($student->id, DB::table('sessions')->where('id', 'live-demo-session')->value('user_id'));
        $this->assertFalse(Auth::guard()->hasUser());
    }

    public function test_no_user_is_left_authenticated_after_success_or_failure(): void
    {
        $this->artisan('demo:reset --force')->assertSuccessful();
        $this->assertFalse(Auth::guard()->hasUser());

        $this->artisan('demo:reset --force')->assertSuccessful();
        $this->assertFalse(Auth::guard()->hasUser());

        $other = Institution::factory()->create();
        $faculty = User::query()->withoutGlobalScopes()->where('email', DemoDataset::FACULTY)->sole();
        AuditEvent::query()->withoutGlobalScopes()->create([
            'institution_id' => $other->id, 'actor_id' => $faculty->id, 'actor_role' => 'faculty',
            'event_type' => 'test.cross_reference', 'auditable_type' => User::class, 'auditable_id' => (string) $faculty->id, 'metadata' => [],
        ]);
        $this->artisan('demo:reset --force')->assertFailed();
        $this->assertFalse(Auth::guard()->hasUser());
    }

    public function test_a_purge_failure_reports_the_stage_and_rolls_back(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $other = Institution::factory()->create();
        $faculty = User::query()->withoutGlobalScopes()->where('email', DemoDataset::FACULTY)->sole();
        AuditEvent::query()->withoutGlobalScopes()->create([
            'institution_id' => $other->id, 'actor_id' => $faculty->id, 'actor_role' => 'faculty',
            'event_type' => 'test.cross_reference', 'auditable_type' => User::class, 'auditable_id' => (string) $faculty->id, 'metadata' => [],
        ]);
        $before = $this->counts();

        Artisan::call('demo:reset', ['--force' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('Demo reset failed while deleting demo users. No data was changed.', $output);
        $this->assertStringNotContainsString('SQLSTATE', $output);
        $this->assertStringNotContainsString('delete from', strtolower($output));
        $this->assertSame($before, $this->counts());
    }

    public function test_the_password_never_appears_in_reset_output(): void
    {
        $this->enableDemoMode('Sup3r-Secret-Demo-Pass');
        $this->artisan('demo:seed')->assertSuccessful();

        Artisan::call('demo:reset', ['--force' => true]);
        $this->assertStringNotContainsString('Sup3r-Secret-Demo-Pass', Artisan::output());

        $this->enableDemoMode('short-pass1');
        Artisan::call('demo:reset', ['--force' => true]);
        $this->assertStringNotContainsString('short-pass1', Artisan::output());
    }

    private function seededInstitution(): Institution
    {
        if (Institution::query()->where('slug', DemoDataset::INSTITUTION_SLUG)->doesntExist()) {
            $this->artisan('demo:seed')->assertSuccessful();
        }

        return Institution::query()->where('slug', DemoDataset::INSTITUTION_SLUG)->sole();
    }

    private function caseFor(string $email, int $number): ClinicalCase
    {
        $studentId = User::query()->withoutGlobalScopes()->where('email', $email)->value('id');

        return ClinicalCase::query()->withoutGlobalScopes()->where('student_id', $studentId)->where('case_number', $number)->sole();
    }

    /** @return array<string, string> */
    private function passwordHashes(): array
    {
        return User::query()->withoutGlobalScopes()->where('email', 'like', '%@pharmalab-demo.test')->orderBy('email')->pluck('password', 'email')->all();
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $counts = [];
        foreach (['institutions', 'users', 'rotations', 'rotation_assignments', 'rotation_case_targets', 'clinical_cases', 'case_versions', 'case_status_transitions', 'case_review_comments', 'audit_events', 'soap_notes', 'case_medications', 'sessions'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
