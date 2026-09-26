<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseVital;
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

class CaseVitalSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_add_row_tap_succeeds_and_sets_the_section_to_recorded(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(),
        ]);

        $response->assertCreated();
        $this->assertCount(1, $case->fresh()->vitals);
        $this->assertSame('recorded', $case->fresh()->vitals_status);
    }

    public function test_an_unrelated_field_can_be_edited_on_a_still_bare_row_via_a_whole_row_resend(): void
    {
        // Regression: useSectionSync resends the whole row on every autosave
        // (not a partial patch), so a still-bare row's edit payload always
        // includes observation_type:null alongside whatever field the
        // student actually touched. UpdateCaseVitalRequest's
        // observation_type rule was 'required' (unlike Store's 'nullable'),
        // so editing e.g. just the note field on a row with no observation
        // type yet 422'd. Found via a live Railway staging check.
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'observation_type' => null, 'recorded_by' => $student->id,
        ]);

        $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'observation_type' => null,
            'note' => 'Still deciding what to record',
        ])->assertOk();
    }

    public function test_a_freshly_created_row_can_be_edited_immediately_using_the_create_responses_own_lock_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $created = $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(),
        ])->assertCreated()->json('vital');

        $this->assertSame(0, $created['lock_version'], 'a freshly created row must report the real DB default lock_version, not null');

        $this->putJson("/student/cases/{$case->id}/vitals/{$created['id']}", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => $created['lock_version'],
            'note' => 'Edited right after creation',
        ])->assertOk();
    }

    public function test_owning_student_can_create_a_vital_row_with_data(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(),
            'observation_type' => 'blood_pressure',
            'value_systolic' => 120,
            'value_diastolic' => 80,
            'unit' => 'mmHg',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('vital.observation_type', 'blood_pressure');
    }

    public function test_blood_pressure_requires_both_systolic_and_diastolic_together(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(),
            'observation_type' => 'blood_pressure',
            'value_systolic' => 120,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('value_diastolic');
    }

    public function test_oxygen_saturation_rejects_a_value_outside_0_to_100(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(),
            'observation_type' => 'oxygen_saturation',
            'value_numeric' => 101,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('value_numeric');
    }

    public function test_replaying_the_same_create_operation_id_does_not_create_a_second_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $operationId = (string) Str::uuid();
        $payload = ['client_operation_id' => $operationId, 'observation_type' => 'pulse', 'value_numeric' => 80];

        $this->postJson("/student/cases/{$case->id}/vitals", $payload)->assertCreated();
        $this->postJson("/student/cases/{$case->id}/vitals", $payload)->assertCreated();

        $this->assertCount(1, $case->fresh()->vitals);
    }

    // The cross-section replay-rejection scenario (reusing a vitals create's
    // client_operation_id against /investigations) needs a second real
    // section endpoint to post to, which doesn't exist until Task 3. That
    // task's own test suite covers it instead, once both endpoints exist.

    public function test_a_single_field_edit_does_not_require_or_erase_other_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $vital = $this->makeVital($case, $student);

        $response = $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'note' => 'Recorded at bedside.',
        ]);

        $response->assertOk();
        $fresh = $vital->fresh();
        $this->assertSame('pulse', $fresh->observation_type);
        $this->assertSame('Recorded at bedside.', $fresh->note);
    }

    public function test_a_stale_base_lock_version_on_a_row_edit_returns_409(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $vital = $this->makeVital($case, $student);

        $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'note' => 'First',
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'note' => 'Conflicting',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('section.id', $vital->id);
        $response->assertJsonPath('section.note', 'First');
        $response->assertJsonPath('section.lock_version', 1);
        $response->assertJsonMissingPath('vital');
    }

    public function test_editing_a_vital_through_a_different_case_id_in_the_url_is_rejected(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherCase = $this->makeCaseFor($institution, $student, 2);
        $vital = $this->makeVital($case, $student);
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$otherCase->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'note' => 'Should not apply',
        ]);

        $response->assertNotFound();
    }

    public function test_owning_student_can_delete_a_vital_row_at_the_correct_lock_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $vital = $this->makeVital($case, $student);

        $this->deleteJson("/student/cases/{$case->id}/vitals/{$vital->id}", ['base_lock_version' => 0])->assertNoContent();

        $this->assertCount(0, $case->fresh()->vitals);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'vitals.deleted',
        ]);
    }

    public function test_deleting_a_vital_row_with_a_stale_base_lock_version_returns_409_and_does_not_delete(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $vital = $this->makeVital($case, $student);
        $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'note' => 'Bumps the lock version.',
        ])->assertOk();

        $response = $this->deleteJson("/student/cases/{$case->id}/vitals/{$vital->id}", ['base_lock_version' => 0]);

        $response->assertStatus(409);
        $this->assertCount(1, $case->fresh()->vitals);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'vitals.deleted']);
    }

    public function test_owning_student_can_mark_vitals_unavailable_with_a_reason(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/vitals-availability", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'vitals_status' => 'unavailable',
            'vitals_unavailable_reason' => 'Not examined at bedside during this review.',
        ]);

        $response->assertOk();
        $this->assertSame('unavailable', $case->fresh()->vitals_status);
    }

    public function test_marking_vitals_unavailable_is_rejected_while_rows_exist(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $this->makeVital($case, $student);

        $response = $this->putJson("/student/cases/{$case->id}/vitals-availability", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'vitals_status' => 'unavailable',
            'vitals_unavailable_reason' => 'Attempted despite existing rows.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('vitals_status');
    }

    public function test_switching_back_to_recorded_clears_the_unavailable_reason(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/vitals-availability", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'vitals_status' => 'unavailable', 'vitals_unavailable_reason' => 'Not examined.',
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/vitals-availability", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'vitals_status' => 'recorded',
        ])->assertOk();

        $this->assertNull($case->fresh()->vitals_unavailable_reason);
    }

    public function test_an_unknown_field_on_create_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(), 'observation_type' => 'pulse', 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_an_unknown_field_on_the_availability_toggle_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/vitals-availability", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'vitals_status' => 'recorded', 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_a_different_student_cannot_create_edit_or_delete_a_vital(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $vital = $this->makeVital($case, $student);
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(), 'observation_type' => 'pulse', 'value_numeric' => 80,
        ])->assertForbidden();

        $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'note' => 'x',
        ])->assertForbidden();

        $this->deleteJson("/student/cases/{$case->id}/vitals/{$vital->id}")->assertForbidden();
    }

    private function makeVital(ClinicalCase $case, User $student): CaseVital
    {
        return CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
        ]);
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);

        return [$institution, $student, $this->makeCaseFor($institution, $student, 1, $status)];
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
