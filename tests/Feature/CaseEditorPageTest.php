<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseClinicalProfile;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseEditorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owning_student_can_open_the_editor_without_creating_a_profile(): void
    {
        [, $student, $case] = $this->makeCase();

        $response = $this->actingAs($student)->get(route('student.cases.edit', $case));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('student/CaseEditor')
            ->where('clinicalCase.id', $case->id)
            ->where('clinicalProfile', null));
        $this->assertNull($case->fresh()->clinicalProfile);
    }

    public function test_editor_reflects_an_existing_profile(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'allergy_status' => 'unknown',
            'last_saved_by' => $student->id,
        ]);

        $this->actingAs($student)->get(route('student.cases.edit', $case))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('clinicalProfile.allergy_status', 'unknown'));
    }

    public function test_a_different_student_cannot_open_the_editor(): void
    {
        [$institution, , $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);

        $this->actingAs($otherStudent)->get(route('student.cases.edit', $case))->assertForbidden();
    }

    public function test_owning_student_can_open_the_editor_for_a_returned_case(): void
    {
        [, $student, $case] = $this->makeCase(CaseStatus::Returned);

        $this->actingAs($student)->get(route('student.cases.edit', $case))->assertOk();
    }

    public function test_owning_student_cannot_open_the_editor_for_a_submitted_case(): void
    {
        [, $student, $case] = $this->makeCase(CaseStatus::Submitted);

        $this->actingAs($student)->get(route('student.cases.edit', $case))->assertForbidden();
    }

    public function test_owning_student_cannot_open_the_editor_for_an_under_review_case(): void
    {
        [, $student, $case] = $this->makeCase(CaseStatus::UnderReview);

        $this->actingAs($student)->get(route('student.cases.edit', $case))->assertForbidden();
    }

    public function test_owning_student_cannot_open_the_editor_for_an_approved_case(): void
    {
        [, $student, $case] = $this->makeCase(CaseStatus::Approved);

        $this->actingAs($student)->get(route('student.cases.edit', $case))->assertForbidden();
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

        return [$institution, $student, ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => $status,
        ])];
    }
}
