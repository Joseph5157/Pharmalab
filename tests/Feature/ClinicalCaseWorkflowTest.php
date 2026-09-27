<?php

namespace Tests\Feature;

use App\Actions\ApproveCase;
use App\Actions\ReopenCase;
use App\Actions\ReturnCase;
use App\Actions\SubmitCase;
use App\Enums\CaseFormVersion;
use App\Enums\CaseStatus;
use App\Models\CaseClinicalActivity;
use App\Models\CaseClinicalProfile;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVersion;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\SoapNote;
use App\Models\User;
use App\Policies\ClinicalCasePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalCaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_case_and_submit_soap_note(): void
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

        $this->actingAs($student);

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
            'form_version' => 'pharmd-case-v1',
            'encounter_date' => '2026-10-15',
            'case_category' => 'Drug Therapy Problem',
            'clinical_site_id' => $site->id,
            'care_setting' => 'inpatient',
            'information_source' => 'case sheet',
            'age_value' => 34,
            'age_unit' => 'years',
            'sex' => 'female',
            'vitals_status' => 'recorded',
            'investigations_status' => 'recorded',
            'medication_chart_status' => 'documented',
        ]);

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Draft->value]);

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'chief_complaints' => [['complaint' => 'Headache', 'duration' => '3 days']],
            'history_present_illness' => 'Three-day headache, no red flags.',
            'diagnoses' => [['label' => 'Tension headache', 'type' => 'provisional']],
            'past_medical_history_none' => true,
            'allergy_status' => 'no_known_allergy',
            'last_saved_by' => $student->id,
        ]);
        CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
        ]);
        CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'test_name' => 'Haemoglobin', 'result_type' => 'numeric', 'result_value' => '13.5',
            'unit' => 'g/dL', 'reference_range_not_provided' => true, 'recorded_by' => $student->id,
        ]);
        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'medication_context' => null, 'generic_name' => 'Paracetamol', 'indication' => 'Headache relief',
            'dose_amount' => '500', 'dose_unit' => 'mg', 'route' => 'oral', 'frequency' => 'TID',
            'status' => 'active', 'recorded_by' => $student->id,
        ]);
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'adr', 'status' => 'no', 'recorded_by' => $student->id,
        ]);
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'counselling', 'status' => 'not_indicated', 'recorded_by' => $student->id,
        ]);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'Patient reports headache for 3 days.',
            'objective' => 'BP 140/90, HR 80.',
            'assessment' => 'Tension-type headache.',
            'plan' => 'Recommend paracetamol 500mg TID.',
            'monitoring_plan' => 'Review pain score at 24h.',
            'drug_related_problem_status' => 'none_identified',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        $submitCase = app(SubmitCase::class);
        $version = $submitCase($student, $case, true);

        $this->assertNotNull($version);
        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
        $this->assertDatabaseHas('case_versions', ['clinical_case_id' => $case->id, 'version_number' => 1]);
        $this->assertDatabaseHas('case_status_transitions', [
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Draft->value,
            'to_status' => CaseStatus::Submitted->value,
        ]);
    }

    public function test_faculty_can_approve_submitted_case(): void
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
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $version = CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($faculty);

        $approveCase = app(ApproveCase::class);
        $approvedVersion = $approveCase($faculty, $case, 'Good work.');

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Approved->value]);
        $this->assertDatabaseHas('case_versions', ['id' => $version->id, 'approved_by' => $faculty->id]);
    }

    public function test_faculty_can_return_case_for_correction(): void
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
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->actingAs($faculty);

        $returnCase = app(ReturnCase::class);
        $returnCase($faculty, $case, 'Please add more detail to the assessment section.');

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Returned->value]);
        $this->assertDatabaseHas('case_status_transitions', [
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Submitted->value,
            'to_status' => CaseStatus::Returned->value,
            'reason' => 'Please add more detail to the assessment section.',
        ]);
    }

    public function test_student_can_resubmit_returned_case(): void
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
            'status' => CaseStatus::Returned,
            'current_revision_number' => 1,
            'form_version' => 'pharmd-case-v1',
            'care_setting' => 'inpatient',
            'encounter_date' => '2026-10-15',
            'information_source' => 'case sheet',
            'age_value' => 34,
            'age_unit' => 'years',
            'sex' => 'female',
            'vitals_status' => 'recorded',
            'investigations_status' => 'recorded',
            'medication_chart_status' => 'documented',
        ]);

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'chief_complaints' => [['complaint' => 'Headache', 'duration' => '3 days']],
            'history_present_illness' => 'Three-day headache, no red flags.',
            'diagnoses' => [['label' => 'Tension headache', 'type' => 'provisional']],
            'past_medical_history_none' => true,
            'allergy_status' => 'no_known_allergy',
            'last_saved_by' => $student->id,
        ]);
        CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
        ]);
        CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'test_name' => 'Haemoglobin', 'result_type' => 'numeric', 'result_value' => '13.5',
            'unit' => 'g/dL', 'reference_range_not_provided' => true, 'recorded_by' => $student->id,
        ]);
        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'medication_context' => null, 'generic_name' => 'Paracetamol', 'indication' => 'Headache relief',
            'dose_amount' => '500', 'dose_unit' => 'mg', 'route' => 'oral', 'frequency' => 'TID',
            'status' => 'active', 'recorded_by' => $student->id,
        ]);
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'adr', 'status' => 'no', 'recorded_by' => $student->id,
        ]);
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'counselling', 'status' => 'not_indicated', 'recorded_by' => $student->id,
        ]);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'Updated subjective.',
            'objective' => 'Updated objective.',
            'assessment' => 'Updated assessment with more detail.',
            'plan' => 'Updated plan.',
            'monitoring_plan' => 'Review pain score at 24h.',
            'drug_related_problem_status' => 'none_identified',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        $this->actingAs($student);

        $submitCase = app(SubmitCase::class);
        $version = $submitCase($student, $case, true);

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
        $this->assertDatabaseHas('case_versions', ['clinical_case_id' => $case->id, 'version_number' => 2]);
    }

    public function test_creating_a_case_persists_new_context_fields_and_always_uses_the_server_form_version(): void
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

        $this->actingAs($student);

        $response = $this->post(route('student.cases.store'), [
            'rotation_assignment_id' => $assignment->id,
            'care_setting' => 'inpatient',
            'hospital_day_at_first_review' => 3,
            'information_source' => 'case sheet',
            'weight_kg' => 65.25,
            'height_cm' => 168,
            'form_version' => 'spoofed-version',
        ]);

        $response->assertRedirect();

        $case = ClinicalCase::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame(CaseFormVersion::PharmdV1, $case->form_version);
        $this->assertSame('inpatient', $case->care_setting);
        $this->assertSame(3, $case->hospital_day_at_first_review);
        $this->assertSame('65.25', $case->weight_kg);
    }

    public function test_faculty_can_reopen_an_approved_case_and_the_prior_version_is_untouched(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Approved,
            'current_revision_number' => 1,
        ]);

        $version = CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'original']],
            'snapshot_hash' => hash('sha256', 'original'),
            'submitted_by' => $student->id,
            'submitted_at' => now()->subDay(),
            'approved_by' => $faculty->id,
            'approved_at' => now()->subHour(),
        ]);

        $this->actingAs($faculty);

        $reopenCase = app(ReopenCase::class);
        $reopenCase($faculty, $case, 'Diagnosis needs revisiting after new labs.');

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Returned->value]);
        $this->assertDatabaseHas('case_status_transitions', [
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Approved->value,
            'to_status' => CaseStatus::Returned->value,
            'case_version_id' => $version->id,
            'reason' => 'Diagnosis needs revisiting after new labs.',
        ]);
        $this->assertDatabaseHas('case_versions', [
            'id' => $version->id,
            'approved_by' => $faculty->id,
            'snapshot_hash' => hash('sha256', 'original'),
        ]);
    }

    public function test_reopen_is_rejected_when_the_case_is_not_approved(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
        ]);

        $this->assertFalse((new ClinicalCasePolicy)->reopen($faculty, $case));
    }

    /** @return array{Institution, User, User, RotationAssignment} */
    private function setupAssignment(): array
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

        return [$institution, $student, $faculty, $assignment];
    }
}
