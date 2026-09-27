<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\ClinicalActivityType;
use App\Models\CaseClinicalActivity;
use App\Models\CaseClinicalProfile;
use App\Models\CaseVital;
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

    public function test_editor_page_includes_vitals_investigations_and_medications_with_their_own_lock_columns(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
        ]);
        $this->actingAs($student);

        $response = $this->get("/student/cases/{$case->id}/edit");

        $response->assertInertia(fn ($page) => $page
            ->has('vitals', 1)
            ->where('vitals.0.observation_type', 'pulse')
            ->has('investigations', 0)
            ->has('medications', 0)
            ->where('context.vitals_status', null)
            ->where('context.vitals_availability_lock_version', 0)
            ->where('context.investigations_availability_lock_version', 0)
            ->where('context.medication_chart_availability_lock_version', 0));
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

    public function test_editor_page_includes_the_current_soap_note(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id, 'revision_number' => 1,
            'subjective' => 'Headache.', 'author_id' => $student->id, 'last_saved_by' => $student->id,
        ]);
        $this->actingAs($student);

        $response = $this->get("/student/cases/{$case->id}/edit");

        $response->assertInertia(fn ($page) => $page->where('soap.subjective', 'Headache.'));
    }

    public function test_the_old_soap_page_route_no_longer_exists(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        // The URI itself is now taken by the renamed PUT .../soap sync route
        // (Task 6 retires GET .../soap entirely rather than leaving it as a
        // second, dead route), so a GET to it is correctly 405 Method Not
        // Allowed, not 404 — the old SoapEditor.vue page it used to render is
        // gone, and no route serves it as a GET.
        $this->get("/student/cases/{$case->id}/soap")->assertStatus(405);
    }

    public function test_editor_page_includes_clinical_activities_split_by_shape(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Intervention->value, 'recorded_by' => $student->id,
        ]);
        $this->actingAs($student);

        $response = $this->get("/student/cases/{$case->id}/edit");

        $response->assertInertia(fn ($page) => $page
            ->has('interventions', 1)
            ->has('monitoringFollowUps', 0)
            ->where('adr', null)
            ->where('counselling', null));
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
