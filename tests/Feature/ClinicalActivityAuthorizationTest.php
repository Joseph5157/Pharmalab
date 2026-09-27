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

class ClinicalActivityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_from_another_institution_gets_404_on_every_activity_and_soap_endpoint(): void
    {
        [, $student, $case] = $this->makeAssignedCase();
        $activity = CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Intervention->value, 'recorded_by' => $student->id,
        ]);
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);
        $this->actingAs($otherStudent);

        $this->putJson("/student/cases/{$case->id}/soap", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertNotFound();
        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'no'])->assertNotFound();
        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'not_indicated'])->assertNotFound();
        $this->postJson("/student/cases/{$case->id}/clinical-activities", ['client_operation_id' => (string) Str::uuid(), 'activity_type' => 'intervention'])->assertNotFound();
        $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertNotFound();
        $this->deleteJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}")->assertNotFound();
    }

    public function test_the_owning_student_cannot_sync_soap_or_activities_once_the_case_is_submitted(): void
    {
        [, $student, $case] = $this->makeAssignedCase(CaseStatus::Submitted);
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/soap", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertForbidden();
        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'no'])->assertForbidden();
        $this->postJson("/student/cases/{$case->id}/clinical-activities", ['client_operation_id' => (string) Str::uuid(), 'activity_type' => 'intervention'])->assertForbidden();
    }

    public function test_assigned_faculty_cannot_sync_soap_or_any_clinical_activity(): void
    {
        [, , $case, $faculty] = $this->makeAssignedCase();
        $this->actingAs($faculty);

        $this->putJson("/student/cases/{$case->id}/soap", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertForbidden();
        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'not_indicated'])->assertForbidden();
    }

    /** @return array{Institution, User, ClinicalCase, User} */
    private function makeAssignedCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $faculty = User::factory()->faculty()->create(['institution_id' => $institution->id]);
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'rotation_id' => $rotation->id, 'student_id' => $student->id,
            'primary_preceptor_id' => $faculty->id, 'status' => 'active',
        ]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id, 'case_number' => 1, 'status' => $status,
        ]);

        return [$institution, $student, $case, $faculty];
    }
}
