<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\MedicationStatus;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
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

class RepeatableRowAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_from_another_institution_gets_404_not_403_on_every_row_endpoint(): void
    {
        [, $student, $case] = $this->makeAssignedCase();
        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'pulse',
            'value_numeric' => 80,
            'recorded_by' => $student->id,
        ]);
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);
        $this->actingAs($otherStudent);

        $this->postJson("/student/cases/{$case->id}/vitals", ['client_operation_id' => (string) Str::uuid()])->assertNotFound();
        $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertNotFound();
        $this->deleteJson("/student/cases/{$case->id}/vitals/{$vital->id}")->assertNotFound();
    }

    public function test_the_owning_student_cannot_create_edit_or_delete_rows_once_the_case_is_submitted(): void
    {
        [, $student, $case] = $this->makeAssignedCase(CaseStatus::Submitted);
        $investigation = CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id,
            'clinical_case_id' => $case->id,
            'test_name' => 'Sodium',
            'result_type' => 'numeric',
            'result_value' => '140',
            'recorded_by' => $student->id,
        ]);
        $this->actingAs($student);

        $this->postJson("/student/cases/{$case->id}/investigations", ['client_operation_id' => (string) Str::uuid()])->assertForbidden();
        $this->putJson("/student/cases/{$case->id}/investigations/{$investigation->id}", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertForbidden();
        $this->deleteJson("/student/cases/{$case->id}/investigations/{$investigation->id}")->assertForbidden();
    }

    public function test_assigned_faculty_cannot_create_edit_or_delete_medication_rows(): void
    {
        [, $student, $case, $faculty] = $this->makeAssignedCase();
        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id,
            'clinical_case_id' => $case->id,
            'generic_name' => 'Metformin',
            'status' => MedicationStatus::Active->value,
            'recorded_by' => $student->id,
        ]);
        $this->actingAs($faculty);

        $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(),
            'generic_name' => 'x',
            'status' => MedicationStatus::Active->value,
        ])->assertForbidden();
        $this->putJson("/student/cases/{$case->id}/medications/{$medication->id}", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertForbidden();
        $this->deleteJson("/student/cases/{$case->id}/medications/{$medication->id}")->assertForbidden();
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
