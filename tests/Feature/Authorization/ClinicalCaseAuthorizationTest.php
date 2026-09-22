<?php

namespace Tests\Feature\Authorization;

use App\Enums\CaseStatus;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalCaseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_cannot_access_faculty_routes(): void
    {
        [$institution, $student, $faculty, $assignment, $site] = $this->setupInstitution();
        $this->actingAs($student);

        $case = $this->createCase($institution, $student, $assignment, CaseStatus::Draft);

        $this->get(route('faculty.reviews.index'))->assertForbidden();
        $this->get(route('faculty.reviews.show', $case))->assertForbidden();
        $this->post(route('faculty.reviews.approve', $case))->assertForbidden();
        $this->post(route('faculty.reviews.return', $case))->assertForbidden();
    }

    public function test_faculty_cannot_access_student_case_routes(): void
    {
        [$institution, $student, $faculty] = $this->setupInstitution();
        $this->actingAs($faculty);

        $this->get(route('student.cases.index'))->assertForbidden();
        $this->post(route('student.cases.store'))->assertForbidden();
        $this->get(route('student.portfolio'))->assertForbidden();
    }

    public function test_student_cannot_view_other_students_cases(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($student);

        $otherCase = $this->createCase($institution, $otherStudent, $assignment, CaseStatus::Draft);

        $this->get(route('student.cases.show', $otherCase))->assertForbidden();
    }

    public function test_faculty_cannot_review_cross_institution_cases(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);
        $this->actingAs($faculty);

        $otherCase = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $otherInstitution->id,
            'student_id' => $otherStudent->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
        ]);

        $this->get(route('faculty.reviews.show', $otherCase))->assertNotFound();
        $this->post(route('faculty.reviews.approve', $otherCase))->assertNotFound();
        $this->post(route('faculty.reviews.return', $otherCase))->assertNotFound();
    }

    public function test_student_can_only_submit_own_draft_cases(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($student);

        $otherCase = $this->createCase($institution, $otherStudent, $assignment, CaseStatus::Draft);

        $this->post(route('student.cases.submit', $otherCase))->assertForbidden();
        $this->assertDatabaseHas('clinical_cases', ['id' => $otherCase->id, 'status' => CaseStatus::Draft->value]);
    }

    public function test_faculty_can_only_review_assigned_cases(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $otherFaculty = User::factory()->faculty()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherFaculty);

        $case = $this->createCase($institution, $student, $assignment, CaseStatus::Submitted);

        $this->get(route('faculty.reviews.show', $case))->assertForbidden();
    }

    /** @return array{Institution, User, User, RotationAssignment, ClinicalSite} */
    private function setupInstitution(): array
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

        return [$institution, $student, $faculty, $assignment, $site];
    }

    private function createCase(Institution $institution, User $student, RotationAssignment $assignment, CaseStatus $status): ClinicalCase
    {
        return ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => $status,
        ]);
    }
}
