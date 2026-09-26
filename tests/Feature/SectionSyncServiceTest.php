<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\SyncOperation;
use App\Models\User;
use App\Services\SectionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SectionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_idempotently_and_records_the_syncable_target(): void
    {
        [, $student, $case] = $this->makeCase();
        $id = (string) Str::uuid();
        $service = app(SectionSyncService::class);
        $service->sync($case, $student, 'case_context', $id, 0, ['case_category' => 'DTP'], null, false);
        $replay = $service->sync($case, $student, 'case_context', $id, 0, ['case_category' => 'other'], null, false);
        $this->assertSame('saved', $replay['status']);
        $this->assertSame('DTP', $case->fresh()->case_category);
        $this->assertSame(1, $case->fresh()->lock_version);
        $this->assertDatabaseHas('sync_operations', ['client_operation_id' => $id, 'syncable_type' => ClinicalCase::class, 'syncable_id' => $case->id]);
    }

    public function test_rejects_a_reused_operation_id_for_a_different_section(): void
    {
        [, $student, $case] = $this->makeCase();
        $id = (string) Str::uuid();
        $service = app(SectionSyncService::class);
        $service->sync($case, $student, 'case_context', $id, 0, ['case_category' => 'DTP'], null, false);
        $this->expectException(HttpException::class);
        $service->sync($case, $student, 'vitals_availability', $id, 0, ['vitals_status' => 'unavailable'], null, false);
    }

    public function test_independent_case_sections_do_not_false_conflict(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);
        $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'DTP'], null, false);
        $result = $service->sync($case, $student, 'vitals_availability', (string) Str::uuid(), 0, ['vitals_status' => 'unavailable'], null, false);
        $this->assertSame('saved', $result['status']);
        $this->assertSame(1, $case->fresh()->lock_version);
        $this->assertSame(1, $case->fresh()->vitals_availability_lock_version);
    }

    public function test_stale_versions_return_a_conflict_without_mutating(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);
        $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'first'], null, false);
        $result = $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'second'], null, false);
        $this->assertSame('conflict', $result['status']);
        $this->assertSame(409, $result['httpStatus']);
        $this->assertSame('first', $case->fresh()->case_category);
    }

    public function test_use_server_resolution_does_not_bump_the_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);
        $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'server'], null, false);
        $result = $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'local'], 'use_server', false);
        $this->assertSame('resolved_server', $result['status']);
        $this->assertSame(1, $case->fresh()->lock_version);
        $this->assertSame('server', $case->fresh()->case_category);
    }

    public function test_replace_server_requires_confirmation(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);
        $this->expectException(HttpException::class);
        $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'forced'], 'replace_server', false);
    }

    public function test_confirmed_replace_server_updates_and_audits(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);
        $result = $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'forced'], 'replace_server', true);
        $this->assertSame('resolved_replaced', $result['status']);
        $this->assertSame('forced', $case->fresh()->case_category);
        $this->assertDatabaseHas('audit_events', ['auditable_id' => $case->id, 'event_type' => 'case_context.conflict_resolved']);
    }

    public function test_unknown_sections_are_rejected_by_the_allow_list(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(SectionSyncService::class)->modelClassForSection('not_allowed');
    }

    public function test_operation_id_is_unique_per_user(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);
        $id = (string) Str::uuid();
        $service->sync($case, $student, 'case_context', $id, 0, ['case_category' => 'DTP'], null, false);
        $this->assertSame(1, SyncOperation::query()->where('user_id', $student->id)->where('client_operation_id', $id)->count());
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'rotation_id' => $rotation->id, 'student_id' => $student->id, 'primary_preceptor_id' => $student->id, 'status' => 'active']);

        return [$institution, $student, ClinicalCase::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'student_id' => $student->id, 'rotation_assignment_id' => $assignment->id, 'case_number' => 1, 'status' => CaseStatus::Draft])];
    }
}
