<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseInvestigation;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaseInvestigationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_add_row_tap_succeeds_and_sets_the_section_to_recorded(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->postJson("/student/cases/{$case->id}/investigations", [
            'client_operation_id' => (string) Str::uuid(),
        ])->assertCreated();

        $this->assertSame('recorded', $case->fresh()->investigations_status);
    }

    public function test_a_freshly_created_row_can_be_edited_immediately_using_the_create_responses_own_lock_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $created = $this->postJson("/student/cases/{$case->id}/investigations", [
            'client_operation_id' => (string) Str::uuid(),
        ])->assertCreated()->json('investigation');

        $this->assertSame(0, $created['lock_version'], 'a freshly created row must report the real DB default lock_version, not null');

        $this->putJson("/student/cases/{$case->id}/investigations/{$created['id']}", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => $created['lock_version'],
            'interpretation' => 'Edited right after creation',
        ])->assertOk();
    }

    public function test_owning_student_can_create_an_investigation_row_with_data(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/investigations", [
            'client_operation_id' => (string) Str::uuid(),
            'test_name' => 'Haemoglobin',
            'result_type' => 'numeric',
            'result_value' => '13.5',
            'unit' => 'g/dL',
        ]);

        $response->assertCreated();
        $this->assertCount(1, $case->fresh()->investigations);
    }

    public function test_unit_not_stated_and_reference_range_not_provided_can_be_recorded(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/investigations", [
            'client_operation_id' => (string) Str::uuid(),
            'test_name' => 'Random glucose', 'result_type' => 'numeric', 'result_value' => '110',
            'unit_not_stated' => true, 'reference_range_not_provided' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('investigation.unit_not_stated', true);
        $response->assertJsonPath('investigation.reference_range_not_provided', true);
    }

    public function test_replaying_the_same_create_operation_id_does_not_create_a_second_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $operationId = (string) Str::uuid();
        $payload = ['client_operation_id' => $operationId, 'test_name' => 'Sodium', 'result_type' => 'numeric', 'result_value' => '140'];

        $this->postJson("/student/cases/{$case->id}/investigations", $payload)->assertCreated();
        $this->postJson("/student/cases/{$case->id}/investigations", $payload)->assertCreated();

        $this->assertCount(1, $case->fresh()->investigations);
    }

    public function test_a_single_field_edit_does_not_require_or_erase_other_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $investigation = $this->makeInvestigation($case, $student);

        $response = $this->putJson("/student/cases/{$case->id}/investigations/{$investigation->id}", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'interpretation' => 'Within expected range for this ward context.',
        ]);

        $response->assertOk();
        $fresh = $investigation->fresh();
        $this->assertSame('Sodium', $fresh->test_name);
        $this->assertSame('Within expected range for this ward context.', $fresh->interpretation);
    }

    public function test_a_stale_base_lock_version_on_a_row_edit_returns_409(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $investigation = $this->makeInvestigation($case, $student);

        $this->putJson("/student/cases/{$case->id}/investigations/{$investigation->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'interpretation' => 'First',
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/investigations/{$investigation->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'interpretation' => 'Conflicting',
        ])->assertStatus(409)
            ->assertJsonPath('section.id', $investigation->id)
            ->assertJsonPath('section.interpretation', 'First')
            ->assertJsonPath('section.lock_version', 1)
            ->assertJsonMissingPath('investigation');
    }

    public function test_editing_an_investigation_through_a_different_case_id_in_the_url_is_rejected(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherCase = $this->makeCaseFor($institution, $student, 2);
        $investigation = $this->makeInvestigation($case, $student);
        $this->actingAs($student);

        $this->putJson("/student/cases/{$otherCase->id}/investigations/{$investigation->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'interpretation' => 'Should not apply',
        ])->assertNotFound();
    }

    public function test_owning_student_can_delete_an_investigation_row_at_the_correct_lock_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $investigation = $this->makeInvestigation($case, $student);

        $this->deleteJson("/student/cases/{$case->id}/investigations/{$investigation->id}", ['base_lock_version' => 0])->assertNoContent();

        $this->assertCount(0, $case->fresh()->investigations);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'investigations.deleted']);
    }

    public function test_deleting_an_investigation_row_with_a_stale_base_lock_version_returns_409_and_does_not_delete(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $investigation = $this->makeInvestigation($case, $student);
        $this->putJson("/student/cases/{$case->id}/investigations/{$investigation->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'interpretation' => 'Bumps the lock version.',
        ])->assertOk();

        $response = $this->deleteJson("/student/cases/{$case->id}/investigations/{$investigation->id}", ['base_lock_version' => 0]);

        $response->assertStatus(409);
        $this->assertCount(1, $case->fresh()->investigations);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'investigations.deleted']);
    }

    public function test_marking_investigations_unavailable_is_rejected_while_rows_exist(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $this->makeInvestigation($case, $student);

        $response = $this->putJson("/student/cases/{$case->id}/investigations-availability", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'investigations_status' => 'unavailable', 'investigations_unavailable_reason' => 'Attempted despite rows.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('investigations_status');
    }

    public function test_owning_student_can_mark_investigations_unavailable_with_a_reason(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/investigations-availability", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'investigations_status' => 'unavailable',
            'investigations_unavailable_reason' => 'No investigations ordered for this review.',
        ]);

        $response->assertOk();
        $this->assertSame('unavailable', $case->fresh()->investigations_status);
    }

    public function test_a_different_student_cannot_create_edit_or_delete_an_investigation(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $investigation = $this->makeInvestigation($case, $student);
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->postJson("/student/cases/{$case->id}/investigations", [
            'client_operation_id' => (string) Str::uuid(), 'test_name' => 'Sodium', 'result_type' => 'numeric', 'result_value' => '140',
        ])->assertForbidden();

        $this->putJson("/student/cases/{$case->id}/investigations/{$investigation->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'interpretation' => 'x',
        ])->assertForbidden();

        $this->deleteJson("/student/cases/{$case->id}/investigations/{$investigation->id}")->assertForbidden();
    }

    public function test_reusing_a_create_operation_id_against_a_different_section_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $operationId = (string) Str::uuid();

        $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => $operationId, 'observation_type' => 'pulse', 'value_numeric' => 80,
        ])->assertCreated();

        $this->postJson("/student/cases/{$case->id}/investigations", [
            'client_operation_id' => $operationId, 'test_name' => 'Sodium', 'result_type' => 'numeric', 'result_value' => '140',
        ])->assertStatus(409);
    }

    private function makeInvestigation(ClinicalCase $case, User $student): CaseInvestigation
    {
        return CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'test_name' => 'Sodium', 'result_type' => 'numeric', 'result_value' => '140', 'recorded_by' => $student->id,
        ]);
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = $this->makeCaseFor($institution, $student, 1, $status);

        return [$institution, $student, $case];
    }

    private function makeCaseFor(Institution $institution, User $student, int $caseNumber, CaseStatus $status = CaseStatus::Draft): ClinicalCase
    {
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP'.$caseNumber, 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD'.$caseNumber, 'duration_years' => 6, 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'rotation_id' => $rotation->id,
            'student_id' => $student->id, 'primary_preceptor_id' => $student->id, 'status' => 'active',
        ]);

        return ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id, 'case_number' => $caseNumber, 'status' => $status,
        ]);
    }
}
