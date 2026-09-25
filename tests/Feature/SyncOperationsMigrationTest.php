<?php

namespace Tests\Feature;

use App\Models\CaseDraftNote;
use App\Models\Institution;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncOperationsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = 'database/migrations/2026_09_28_000001_make_sync_operations_polymorphic.php';

    public function test_polymorphic_columns_and_nullable_legacy_foreign_key_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('sync_operations', ['case_draft_note_id', 'syncable_type', 'syncable_id']));
    }

    public function test_exact_path_rollback_and_remigration_preserve_legacy_rows(): void
    {
        [$institution, $student, $draft] = $this->legacy();
        $operation = SyncOperation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'user_id' => $student->id, 'case_draft_note_id' => $draft->id, 'client_operation_id' => (string) Str::uuid(), 'section_key' => 'case_draft_note', 'base_lock_version' => 0, 'result_status' => 'saved', 'server_version' => 1]);
        Artisan::call('migrate:rollback', ['--path' => self::PATH]);
        Artisan::call('migrate', ['--path' => self::PATH]);
        $this->assertTrue(Schema::hasColumns('sync_operations', ['syncable_type', 'syncable_id']));
        $this->assertSame($draft->id, SyncOperation::query()->withoutGlobalScopes()->findOrFail($operation->id)->case_draft_note_id);
    }

    public function test_exact_path_rollback_refuses_when_generalized_rows_exist_without_losing_data(): void
    {
        [$institution, $student] = $this->legacy();
        SyncOperation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'user_id' => $student->id, 'case_draft_note_id' => null, 'syncable_type' => 'App\\Models\\ClinicalCase', 'syncable_id' => (string) Str::ulid(), 'client_operation_id' => (string) Str::uuid(), 'section_key' => 'case_context', 'base_lock_version' => 0, 'result_status' => 'saved', 'server_version' => 1]);
        try { Artisan::call('migrate:rollback', ['--path' => self::PATH]); $this->fail('rollback should refuse'); } catch (\Throwable $e) { $this->assertStringContainsString('Cannot roll back: generalized sync_operations rows exist', $e->getMessage()); }
        $this->assertTrue(Schema::hasColumns('sync_operations', ['syncable_type', 'syncable_id']));
        $this->assertSame(1, SyncOperation::query()->withoutGlobalScopes()->count());
    }

    /** @return array{Institution, User, CaseDraftNote} */
    private function legacy(): array
    {
        $institution = Institution::factory()->create(); $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $draft = CaseDraftNote::query()->withoutGlobalScopes()->create(['case_id' => (string) Str::ulid(), 'student_id' => $student->id, 'institution_id' => $institution->id, 'content' => 'legacy']);
        return [$institution, $student, $draft];
    }
}
