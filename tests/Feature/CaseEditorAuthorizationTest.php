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

class CaseEditorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_from_another_institution_cannot_sync_case_context_or_clinical_profile(): void
    {
        [, , $case] = $this->makeAssignedCase();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);
        $this->actingAs($otherStudent);

        $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'care_setting' => 'inpatient',
        ])->assertNotFound();

        $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'allergy_status' => 'unknown',
        ])->assertNotFound();
    }

    public function test_the_owning_student_cannot_sync_either_section_once_the_case_is_submitted(): void
    {
        [, $student, $case] = $this->makeAssignedCase(CaseStatus::Submitted);
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'care_setting' => 'inpatient',
        ])->assertForbidden();

        $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'allergy_status' => 'unknown',
        ])->assertForbidden();
    }

    public function test_assigned_faculty_can_open_the_editor_but_cannot_sync_either_section(): void
    {
        [, $student, $case, $faculty] = $this->makeAssignedCase();
        $this->actingAs($faculty);

        $this->get("/student/cases/{$case->id}/edit")->assertForbidden();

        $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'care_setting' => 'inpatient',
        ])->assertForbidden();
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
            'institution_id' => $institution->id,
            'rotation_id' => $rotation->id,
            'student_id' => $student->id,
            'primary_preceptor_id' => $faculty->id,
            'status' => 'active',
        ]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => $status,
        ]);

        return [$institution, $student, $case, $faculty];
    }
}
