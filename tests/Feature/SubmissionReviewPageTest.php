<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\SoapNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionReviewPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owning_student_sees_missing_sections_on_an_incomplete_draft(): void
    {
        [, $student, $case] = $this->makeCase(CaseStatus::Draft);

        $this->actingAs($student)->get(route('student.cases.submission-review', $case))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student/SubmissionReview')
                ->where('sectionCompletion.soap', false)
                ->has('submissionErrors'));
    }

    public function test_review_is_forbidden_once_the_case_has_left_draft_or_returned_status(): void
    {
        [, $student, $case] = $this->makeCase(CaseStatus::Submitted);

        $this->actingAs($student)->get(route('student.cases.submission-review', $case))
            ->assertForbidden();
    }

    public function test_a_returned_case_can_still_be_reviewed(): void
    {
        [, $student, $case] = $this->makeCase(CaseStatus::Returned);

        $this->actingAs($student)->get(route('student.cases.submission-review', $case))
            ->assertOk();
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status): array
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
            'current_revision_number' => 1,
        ]);
        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        return [$institution, $student, $case];
    }
}
