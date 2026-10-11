<?php

namespace Tests\Feature;

use App\Demo\DemoDataset;
use App\Demo\DemoPurgeFailedException;
use App\Demo\DemoPurger;
use App\Demo\DemoRefusedException;
use App\Models\AuditEvent;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EnablesDemoMode;
use Tests\TestCase;

class DemoPurgerTest extends TestCase
{
    use EnablesDemoMode, RefreshDatabase;

    public function test_every_institution_scoped_or_user_linked_table_is_in_the_purge_registry(): void
    {
        $registered = array_column(DemoPurger::registry(), 'table');

        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (in_array($table, DemoPurger::EXEMPT_TABLES, true) || array_key_exists($table, DemoPurger::CASCADE_TABLES)) {
                continue;
            }
            if (Schema::hasColumn($table, 'institution_id') || Schema::hasColumn($table, 'user_id')) {
                $this->assertContains($table, $registered, "Table {$table} links to demo data but has no purge strategy.");
            }
        }
    }

    public function test_every_foreign_key_into_a_purged_table_has_a_deletion_strategy(): void
    {
        $registered = array_column(DemoPurger::registry(), 'table');

        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (in_array($table, DemoPurger::EXEMPT_TABLES, true) || in_array($table, $registered, true)) {
                continue;
            }
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (! in_array($foreignKey['foreign_table'], $registered, true)) {
                    continue;
                }
                $cascades = (DemoPurger::CASCADE_TABLES[$table] ?? null) === $foreignKey['foreign_table']
                    && strtolower((string) $foreignKey['on_delete']) === 'cascade';
                $this->assertTrue($cascades, "{$table}.".implode(',', $foreignKey['columns'])." references {$foreignKey['foreign_table']} without a registered deletion strategy.");
            }
        }
    }

    public function test_the_registry_deletes_every_child_before_its_parent(): void
    {
        $position = array_flip(array_column(DemoPurger::registry(), 'table'));

        foreach (array_keys($position) as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $parent = $foreignKey['foreign_table'];
                if ($parent !== $table && isset($position[$parent])) {
                    $this->assertLessThan($position[$parent], $position[$table], "{$table} must be purged before {$parent}.");
                }
            }
        }
    }

    public function test_assert_safe_refuses_a_non_demo_domain_user(): void
    {
        $institution = $this->seedDemo();
        User::factory()->create(['institution_id' => $institution->id, 'email' => 'intruder@example.com']);

        $this->expectException(DemoRefusedException::class);
        $this->expectExceptionMessage('intruder@example.com');

        app(DemoPurger::class)->assertSafe($institution);
    }

    public function test_assert_safe_refuses_an_extra_demo_domain_user(): void
    {
        $institution = $this->seedDemo();
        User::factory()->create(['institution_id' => $institution->id, 'email' => 'extra@pharmalab-demo.test']);

        $this->expectException(DemoRefusedException::class);
        $this->expectExceptionMessage('extra@pharmalab-demo.test');

        app(DemoPurger::class)->assertSafe($institution);
    }

    public function test_assert_safe_refuses_an_allowlisted_account_linked_to_another_institution(): void
    {
        $institution = $this->seedDemo();
        $other = Institution::factory()->create();
        User::query()->withoutGlobalScopes()->where('email', DemoDataset::STUDENT2)->update(['institution_id' => $other->id]);

        $this->expectException(DemoRefusedException::class);
        $this->expectExceptionMessage('student2@pharmalab-demo.test');

        app(DemoPurger::class)->assertSafe($institution);
    }

    public function test_assert_safe_accepts_a_subset_of_the_allowlist(): void
    {
        $institution = Institution::query()->create(['slug' => DemoDataset::INSTITUTION_SLUG, 'name' => DemoDataset::INSTITUTION_NAME, 'status' => 'active', 'timezone' => 'Asia/Kolkata']);
        User::factory()->create(['institution_id' => $institution->id, 'email' => DemoDataset::ADMIN]);

        app(DemoPurger::class)->assertSafe($institution);

        $this->addToAssertionCount(1);
    }

    public function test_purge_removes_every_demo_row_and_nothing_else(): void
    {
        $other = Institution::factory()->create();
        $otherUser = User::factory()->create(['institution_id' => $other->id]);
        $institution = $this->seedDemo();

        app(DemoPurger::class)->purge($institution);

        foreach (DemoPurger::registry() as $stage) {
            if ($stage['by'] === 'institution') {
                $this->assertSame(0, DB::table($stage['table'])->where('institution_id', $institution->id)->count(), $stage['table']);
            }
        }
        $this->assertNull(Institution::query()->find($institution->id));
        $this->assertNotNull(Institution::query()->find($other->id));
        $this->assertNotNull(User::query()->withoutGlobalScopes()->find($otherUser->id));
    }

    public function test_purge_refuses_any_institution_other_than_the_demo_institution(): void
    {
        $this->seedDemo();
        $real = Institution::factory()->create();
        $realUser = User::factory()->create(['institution_id' => $real->id]);

        try {
            app(DemoPurger::class)->purge($real);
            $this->fail('Expected the purge to refuse a non-demo institution.');
        } catch (DemoRefusedException) {
        }

        $this->assertNotNull(Institution::query()->find($real->id));
        $this->assertNotNull(User::query()->withoutGlobalScopes()->find($realUser->id));
    }

    public function test_a_user_added_after_the_safety_check_makes_the_purge_fail_instead_of_being_deleted(): void
    {
        $institution = $this->seedDemo();
        $inserted = false;
        // Simulates a person committed by another request mid-purge: the
        // user appears after assertSafe() ran and the demo user ids were read.
        DB::listen(function ($query) use ($institution, &$inserted): void {
            if (! $inserted && str_contains($query->sql, 'delete from "sessions"')) {
                $inserted = true;
                DB::table('users')->insert([
                    'institution_id' => $institution->id, 'name' => 'Late Person', 'email' => 'late@example.com',
                    'password' => 'not-a-real-hash', 'role' => 'student', 'status' => 'active',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        try {
            DB::transaction(fn () => app(DemoPurger::class)->purge($institution));
            $this->fail('Expected the purge to fail rather than delete an unchecked user.');
        } catch (DemoPurgeFailedException $e) {
            $this->assertSame('demo institution', $e->label);
        }

        $this->assertTrue($inserted);
        $this->assertNotNull(Institution::query()->find($institution->id));
    }

    public function test_direct_purge_with_demo_mode_disabled_changes_nothing(): void
    {
        $institution = $this->seedDemo();
        $before = $this->demoRowCounts($institution);
        config(['demo.enabled' => false]);

        try {
            DB::transaction(fn () => app(DemoPurger::class)->purge($institution));
            $this->fail('Expected the purge to refuse while demo mode is disabled.');
        } catch (DemoRefusedException $e) {
            $this->assertStringContainsString('DEMO_MODE is not enabled', $e->getMessage());
        }

        $this->assertSame($before, $this->demoRowCounts($institution));
    }

    public function test_a_stale_institution_object_cannot_bypass_the_current_slug_and_name_check(): void
    {
        $stale = $this->seedDemo();
        $before = $this->demoRowCounts($stale);
        // The row changes after the caller loaded its model: the purge must
        // validate what is in the database now, not the object it was given.
        DB::table('institutions')->where('id', $stale->id)->update(['name' => 'Real College']);

        try {
            DB::transaction(fn () => app(DemoPurger::class)->purge($stale));
            $this->fail('Expected the purge to refuse the renamed institution.');
        } catch (DemoRefusedException) {
        }

        $this->assertSame(DemoDataset::INSTITUTION_NAME, $stale->name);
        $this->assertSame($before, $this->demoRowCounts($stale));
    }

    public function test_purge_removes_sessions_and_password_reset_tokens_of_demo_users_only(): void
    {
        $institution = $this->seedDemo();
        $demoUser = User::query()->withoutGlobalScopes()->where('email', DemoDataset::STUDENT1)->sole();
        $otherUser = User::factory()->create();
        foreach ([$demoUser, $otherUser] as $user) {
            DB::table('sessions')->insert(['id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
            DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'token', 'created_at' => now()]);
        }

        app(DemoPurger::class)->purge($institution);

        $this->assertSame(['session-'.$otherUser->id], DB::table('sessions')->pluck('id')->all());
        $this->assertSame([$otherUser->email], DB::table('password_reset_tokens')->pluck('email')->all());
    }

    public function test_purge_reports_the_failing_stage_label(): void
    {
        $institution = $this->seedDemo();
        $other = Institution::factory()->create();
        $faculty = User::query()->withoutGlobalScopes()->where('email', DemoDataset::FACULTY)->sole();
        // A row outside the demo institution that references a demo user:
        // the restrictive users FK must stop the "demo users" stage.
        AuditEvent::query()->withoutGlobalScopes()->create([
            'institution_id' => $other->id, 'actor_id' => $faculty->id, 'actor_role' => 'faculty',
            'event_type' => 'test.cross_reference', 'auditable_type' => User::class, 'auditable_id' => (string) $faculty->id, 'metadata' => [],
        ]);

        try {
            DB::transaction(fn () => app(DemoPurger::class)->purge($institution));
            $this->fail('Expected the purge to fail.');
        } catch (DemoPurgeFailedException $e) {
            $this->assertSame('demo users', $e->label);
        }

        $this->assertNotNull(Institution::query()->find($institution->id));
    }

    /** @return array<string, int> */
    private function demoRowCounts(Institution $institution): array
    {
        $counts = ['institutions' => DB::table('institutions')->where('id', $institution->id)->count()];
        foreach (['users', 'clinical_cases', 'case_versions', 'audit_events', 'rotations'] as $table) {
            $counts[$table] = DB::table($table)->where('institution_id', $institution->id)->count();
        }

        return $counts;
    }

    private function seedDemo(): Institution
    {
        $this->enableDemoMode();
        $this->artisan('demo:seed')->assertSuccessful();

        return Institution::query()->where('slug', DemoDataset::INSTITUTION_SLUG)->sole();
    }
}
