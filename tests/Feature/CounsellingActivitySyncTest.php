<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
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

class CounsellingActivitySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_indicated_does_not_require_counselling_details(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'not_indicated',
        ]);

        $response->assertOk();
    }

    public function test_performed_requires_topics(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'performed',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('details.topics');
    }

    public function test_planned_requires_topics(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'planned',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('details.topics');
    }

    public function test_performed_with_the_full_field_set_persists_them(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'performed',
            'details' => [
                'topics' => 'Medicine purpose and dosing schedule.',
                'medicine_purpose' => 'Blood pressure control.',
                'administration' => 'One tablet every morning with water.',
                'adherence' => 'Use a daily reminder.',
                'precautions' => 'Avoid grapefruit juice.',
                'adverse_effects' => 'Dizziness on standing.',
                'storage' => 'Store below 25C, away from moisture.',
                'lifestyle_follow_up' => 'Reduce dietary salt.',
                'understanding_checked' => true,
            ],
        ]);

        $response->assertOk();
        $fresh = $case->fresh()->clinicalActivities->first();
        $this->assertSame('Medicine purpose and dosing schedule.', $fresh->details['topics']);
        $this->assertSame('Blood pressure control.', $fresh->details['medicine_purpose']);
        $this->assertTrue($fresh->details['understanding_checked']);
    }

    public function test_a_single_detail_field_edit_preserves_previously_saved_sibling_keys(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'performed',
            'details' => ['topics' => 'Dosing schedule.', 'storage' => 'Store below 25C.'],
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'details' => ['understanding_checked' => true],
        ])->assertOk();

        $fresh = $case->fresh()->clinicalActivities->first();
        $this->assertSame('Dosing schedule.', $fresh->details['topics']);
        $this->assertSame('Store below 25C.', $fresh->details['storage']);
        $this->assertTrue($fresh->details['understanding_checked']);
    }

    public function test_changing_status_away_from_performed_clears_the_hidden_detail_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'performed',
            'details' => ['topics' => 'Dosing schedule.'],
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1, 'status' => 'not_indicated',
        ])->assertOk();

        $this->assertNull($case->fresh()->clinicalActivities->first()->details);
    }

    public function test_a_different_student_cannot_sync_the_counselling_activity(): void
    {
        [$institution, , $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'not_indicated',
        ])->assertForbidden();
    }

    public function test_an_unknown_top_level_field_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'not_indicated', 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_an_unknown_nested_details_key_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'performed',
            'details' => ['topics' => 'Dosing schedule.', 'patient_name' => 'Should be rejected'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('details');
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
