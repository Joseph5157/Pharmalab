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

    public function test_cross_institution_student_cannot_open_another_institutions_submission_review(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);
        $this->actingAs($student);

        $otherCase = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $otherInstitution->id,
            'student_id' => $otherStudent->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
        ]);

        // The institution global scope removes the case from route-model
        // binding for the acting student, so it is not found — the same
        // isolation the existing faculty cross-institution test asserts.
        $this->get(route('student.cases.submission-review', $otherCase))->assertNotFound();
    }

    public function test_cross_institution_student_cannot_submit_another_institutions_case(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);
        $this->actingAs($student);

        $otherCase = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $otherInstitution->id,
            'student_id' => $otherStudent->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
        ]);

        $this->post(route('student.cases.submit', $otherCase), ['deidentification_attested' => true])
            ->assertNotFound();
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

    public function test_student_cannot_open_another_students_submission_review(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($student);

        $otherCase = $this->createCase($institution, $otherStudent, $assignment, CaseStatus::Draft);

        $this->get(route('student.cases.submission-review', $otherCase))->assertForbidden();
    }

    public function test_faculty_cannot_open_or_submit_the_student_submission_routes(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $this->actingAs($faculty);

        $case = $this->createCase($institution, $student, $assignment, CaseStatus::Draft);

        $this->get(route('student.cases.submission-review', $case))->assertForbidden();
        $this->post(route('student.cases.submit', $case))->assertForbidden();
    }

    public function test_submission_review_is_forbidden_once_a_case_leaves_draft_or_returned_status(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $this->actingAs($student);

        foreach ([CaseStatus::Submitted, CaseStatus::UnderReview, CaseStatus::Approved] as $index => $status) {
            $case = $this->createCase($institution, $student, $assignment, $status, $index + 1);

            $this->get(route('student.cases.submission-review', $case))->assertForbidden();
        }
    }

    public function test_submit_is_forbidden_once_a_case_is_under_review_or_approved(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $this->actingAs($student);

        foreach ([CaseStatus::UnderReview, CaseStatus::Approved] as $index => $status) {
            $case = $this->createCase($institution, $student, $assignment, $status, $index + 1);

            $this->post(route('student.cases.submit', $case), ['deidentification_attested' => true])->assertForbidden();
        }
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

    private function createCase(Institution $institution, User $student, RotationAssignment $assignment, CaseStatus $status, int $caseNumber = 1): ClinicalCase
    {
        return ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => $caseNumber,
            'status' => $status,
        ]);
    }
}
