<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\ClinicalActivityType;
use App\Models\CaseClinicalActivity;
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

class RepeatableClinicalActivitySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_add_row_tap_succeeds(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/clinical-activities", [
            'client_operation_id' => (string) Str::uuid(),
            'activity_type' => ClinicalActivityType::Intervention->value,
        ]);

        $response->assertCreated();
        $this->assertCount(1, $case->fresh()->clinicalActivities);
    }

    public function test_creating_an_adr_or_counselling_row_through_the_generic_endpoint_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/clinical-activities", [
            'client_operation_id' => (string) Str::uuid(),
            'activity_type' => ClinicalActivityType::Adr->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('activity_type');
    }

    public function test_editing_an_adr_row_through_the_generic_row_endpoint_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'no',
        ])->assertOk();
        $adr = $case->fresh()->clinicalActivities->firstWhere('activity_type', ClinicalActivityType::Adr);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/{$adr->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'details' => ['problem' => 'x'],
        ]);

        $response->assertNotFound();
    }

    public function test_a_single_detail_field_edit_on_an_intervention_row_preserves_sibling_keys(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Intervention);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'details' => ['problem' => 'Dose too low', 'recommendation' => 'Increase to 1g TID'],
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'details' => ['outcome' => 'accepted'],
        ]);

        $response->assertOk();
        // useSectionSync (the frontend composable ActivityRow.vue uses for
        // this endpoint) hardcodes `body.section` on every successful sync
        // response — this was silently returning `activity` instead until a
        // Task 9 live device pass caught it (the request always saved
        // correctly server-side, but the frontend's own success handler threw
        // reading `body.section.lock_version` and every edit displayed as a
        // silent "Sync failed").
        $response->assertJsonPath('section.details.outcome', 'accepted');
        $fresh = $activity->fresh();
        $this->assertSame('Dose too low', $fresh->details['problem']);
        $this->assertSame('Increase to 1g TID', $fresh->details['recommendation']);
        $this->assertSame('accepted', $fresh->details['outcome']);
    }

    public function test_replaying_the_same_create_operation_id_does_not_create_a_second_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $operationId = (string) Str::uuid();
        $payload = ['client_operation_id' => $operationId, 'activity_type' => ClinicalActivityType::Monitoring->value];

        $this->postJson("/student/cases/{$case->id}/clinical-activities", $payload)->assertCreated();
        $this->postJson("/student/cases/{$case->id}/clinical-activities", $payload)->assertCreated();

        $this->assertCount(1, $case->fresh()->clinicalActivities);
    }

    public function test_owning_student_can_delete_a_monitoring_row_at_the_correct_lock_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Monitoring);

        $this->deleteJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", ['base_lock_version' => 0])->assertNoContent();

        $this->assertCount(0, $case->fresh()->clinicalActivities);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'clinical_activities.deleted']);
    }

    public function test_deleting_a_monitoring_row_with_a_stale_base_lock_version_returns_409_and_does_not_delete(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Monitoring);
        $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'details' => ['notes' => 'Bumps the lock version.'],
        ])->assertOk();

        $response = $this->deleteJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", ['base_lock_version' => 0]);

        $response->assertStatus(409);
        $this->assertCount(1, $case->fresh()->clinicalActivities);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'clinical_activities.deleted']);
    }

    public function test_an_unknown_top_level_field_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Intervention);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_an_unknown_nested_details_key_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Intervention);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'details' => ['problem' => 'Dose too low', 'patient_name' => 'Should be rejected'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('details');
    }

    public function test_a_different_student_cannot_create_edit_or_delete_an_activity_row(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Intervention);
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->postJson("/student/cases/{$case->id}/clinical-activities", [
            'client_operation_id' => (string) Str::uuid(), 'activity_type' => ClinicalActivityType::Intervention->value,
        ])->assertForbidden();

        $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
        ])->assertForbidden();

        $this->deleteJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}")->assertForbidden();
    }

    private function makeActivity(ClinicalCase $case, User $student, ClinicalActivityType $type): CaseClinicalActivity
    {
        return CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'activity_type' => $type->value, 'recorded_by' => $student->id,
        ]);
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'rotation_id' => $rotation->id, 'student_id' => $student->id, 'primary_preceptor_id' => $student->id, 'status' => 'active']);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id, 'case_number' => 1, 'status' => $status,
        ]);

        return [$institution, $student, $case];
    }
}
