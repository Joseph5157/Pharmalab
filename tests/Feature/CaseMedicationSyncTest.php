<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\MedicationStatus;
use App\Models\CaseMedication;
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

class CaseMedicationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_add_row_tap_succeeds_and_sets_the_chart_to_documented(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(),
        ])->assertCreated();

        $this->assertSame('documented', $case->fresh()->medication_chart_status);
    }

    public function test_owning_student_can_create_a_medication_row_with_data(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(),
            'generic_name' => 'Paracetamol',
            'status' => MedicationStatus::Active->value,
        ]);

        $response->assertCreated();
        $this->assertCount(1, $case->fresh()->medications);
    }

    public function test_indication_unclear_can_be_recorded_instead_of_indication_text(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(),
            'generic_name' => 'Amoxicillin', 'status' => MedicationStatus::Active->value,
            'indication_unclear' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('medication.indication_unclear', true);
    }

    public function test_stop_reference_is_required_when_status_is_stopped(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(),
            'generic_name' => 'Amoxicillin',
            'status' => MedicationStatus::Stopped->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('stop_reference');
    }

    public function test_stop_reference_is_required_when_status_is_completed(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(),
            'generic_name' => 'Amoxicillin',
            'status' => MedicationStatus::Completed->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('stop_reference');
    }

    public function test_replaying_the_same_create_operation_id_does_not_create_a_second_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $operationId = (string) Str::uuid();
        $payload = ['client_operation_id' => $operationId, 'generic_name' => 'Metformin', 'status' => MedicationStatus::Active->value];

        $this->postJson("/student/cases/{$case->id}/medications", $payload)->assertCreated();
        $this->postJson("/student/cases/{$case->id}/medications", $payload)->assertCreated();

        $this->assertCount(1, $case->fresh()->medications);
    }

    public function test_a_single_field_edit_does_not_require_or_erase_other_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $medication = $this->makeMedication($case, $student);

        $response = $this->putJson("/student/cases/{$case->id}/medications/{$medication->id}", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'notes' => 'Reviewed with student.',
        ]);

        $response->assertOk();
        $fresh = $medication->fresh();
        $this->assertSame('Metformin', $fresh->generic_name);
        $this->assertSame('Reviewed with student.', $fresh->notes);
    }

    public function test_editing_a_medication_through_a_different_case_id_in_the_url_is_rejected(): void
    {
        [$institution, $student, $case, $assignment] = $this->makeCase();
        $otherCase = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id, 'case_number' => 2, 'status' => CaseStatus::Draft,
        ]);
        $medication = $this->makeMedication($case, $student);
        $this->actingAs($student);

        $this->putJson("/student/cases/{$otherCase->id}/medications/{$medication->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'notes' => 'Should not apply',
        ])->assertNotFound();
    }

    public function test_owning_student_can_delete_a_medication_row_at_the_correct_lock_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $medication = $this->makeMedication($case, $student);

        $this->deleteJson("/student/cases/{$case->id}/medications/{$medication->id}", ['base_lock_version' => 0])->assertNoContent();

        $this->assertCount(0, $case->fresh()->medications);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'medications.deleted']);
    }

    public function test_deleting_a_medication_row_with_a_stale_base_lock_version_returns_409_and_does_not_delete(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $medication = $this->makeMedication($case, $student);
        $this->putJson("/student/cases/{$case->id}/medications/{$medication->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'notes' => 'Bumps the lock version.',
        ])->assertOk();

        $response = $this->deleteJson("/student/cases/{$case->id}/medications/{$medication->id}", ['base_lock_version' => 0]);

        $response->assertStatus(409);
        $this->assertCount(1, $case->fresh()->medications);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'medications.deleted']);
    }

    public function test_marking_no_current_medicines_is_rejected_while_rows_exist(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $this->makeMedication($case, $student);

        $response = $this->putJson("/student/cases/{$case->id}/medication-chart-availability", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'medication_chart_status' => 'none_documented',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('medication_chart_status');
    }

    public function test_owning_student_can_mark_no_current_medicines_documented_with_an_explanation(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/medication-chart-availability", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'medication_chart_status' => 'none_documented',
            'medication_chart_none_reason' => 'No home or chart medicines reported by caregiver.',
        ]);

        $response->assertOk();
        $this->assertSame('none_documented', $case->fresh()->medication_chart_status);
        $this->assertSame('No home or chart medicines reported by caregiver.', $case->fresh()->medication_chart_none_reason);
    }

    public function test_a_different_student_cannot_create_edit_or_delete_a_medication(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $medication = $this->makeMedication($case, $student);
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(), 'generic_name' => 'Ibuprofen', 'status' => MedicationStatus::Active->value,
        ])->assertForbidden();

        $this->putJson("/student/cases/{$case->id}/medications/{$medication->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'notes' => 'x',
        ])->assertForbidden();

        $this->deleteJson("/student/cases/{$case->id}/medications/{$medication->id}")->assertForbidden();
    }

    private function makeMedication(ClinicalCase $case, User $student): CaseMedication
    {
        return CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'generic_name' => 'Metformin', 'status' => MedicationStatus::Active->value, 'recorded_by' => $student->id,
        ]);
    }

    /** @return array{Institution, User, ClinicalCase, RotationAssignment} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'rotation_id' => $rotation->id,
            'student_id' => $student->id, 'primary_preceptor_id' => $student->id, 'status' => 'active',
        ]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id, 'case_number' => 1, 'status' => $status,
        ]);

        return [$institution, $student, $case, $assignment];
    }
}
