<?php

namespace Tests\Feature;

use App\Actions\ApproveCase;
use App\Actions\ReopenCase;
use App\Actions\ReturnCase;
use App\Actions\SubmitCase;
use App\Enums\CaseFormVersion;
use App\Enums\CaseReviewSection;
use App\Enums\CaseStatus;
use App\Models\CaseClinicalActivity;
use App\Models\CaseClinicalProfile;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseReviewComment;
use App\Models\CaseStatusTransition;
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
use App\Services\ClinicalCasePresenter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    public function test_approve_forces_is_flagged_false_even_if_the_caller_sends_true(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
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
        $approveCase($faculty, $case, 'Great work.', [
            // A malicious or buggy client sends is_flagged: true on approve.
            ['section' => 'soap', 'body' => 'Nicely reasoned assessment.', 'is_flagged' => true],
        ]);

        $this->assertDatabaseHas('case_review_comments', [
            'clinical_case_id' => $case->id,
            'section' => 'soap',
            'is_flagged' => false,
        ]);
    }

    public function test_a_failed_approve_leaves_no_transition_or_comment_behind(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

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

        try {
            $approveCase($faculty, $case, 'Duplicate section.', [
                ['section' => 'soap', 'body' => 'First.', 'is_flagged' => false],
                ['section' => 'soap', 'body' => 'Second.', 'is_flagged' => false],
            ]);
            $this->fail('Expected a database exception for the duplicate section.');
        } catch (QueryException) {
            // expected
        }

        $this->assertDatabaseMissing('case_status_transitions', [
            'clinical_case_id' => $case->id,
            'to_status' => CaseStatus::Approved->value,
        ]);
        $this->assertDatabaseMissing('case_review_comments', ['clinical_case_id' => $case->id]);
        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
        $this->assertDatabaseHas('case_versions', [
            'id' => $version->id,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function test_approve_is_rejected_once_the_case_has_already_moved_past_review(): void
    {
        // The pre-Slice-4 ApproveCase had no status guard at all. This proves
        // the new row-lock-and-recheck guard actually rejects an approve
        // attempt once the case is no longer Submitted/UnderReview — the
        // same simulated-race shape as ReturnCase's equivalent test.
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
        ]);

        $this->actingAs($faculty);

        $approveCase = app(ApproveCase::class);

        $this->expectException(HttpException::class);
        $approveCase($faculty, $case, 'Too early.', []);
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

        CaseVersion::query()->withoutGlobalScopes()->create([
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

        $returnCase = app(ReturnCase::class);
        $returnCase($faculty, $case, 'Please add more detail to the assessment section.', [
            ['section' => 'soap', 'body' => 'Please add more detail.', 'is_flagged' => true],
        ]);

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Returned->value]);
        $this->assertDatabaseHas('case_status_transitions', [
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Submitted->value,
            'to_status' => CaseStatus::Returned->value,
            'reason' => 'Please add more detail to the assessment section.',
        ]);
    }

    public function test_return_creates_section_comments_tied_to_the_return_transition(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
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

        $returnCase = app(ReturnCase::class);
        $returnCase($faculty, $case, 'See flagged sections.', [
            ['section' => 'soap', 'body' => 'Expand the assessment.', 'is_flagged' => true],
            ['section' => 'medication_chart', 'body' => 'Looks good.', 'is_flagged' => false],
        ]);

        $transition = CaseStatusTransition::query()->withoutGlobalScopes()
            ->where('clinical_case_id', $case->id)
            ->where('to_status', CaseStatus::Returned->value)
            ->sole();

        $this->assertSame($version->id, $transition->case_version_id);
        $this->assertDatabaseHas('case_review_comments', [
            'case_status_transition_id' => $transition->id,
            'section' => 'soap',
            'is_flagged' => true,
        ]);
        $this->assertDatabaseHas('case_review_comments', [
            'case_status_transition_id' => $transition->id,
            'section' => 'medication_chart',
            'is_flagged' => false,
        ]);
    }

    public function test_return_is_rejected_once_the_case_has_already_moved_past_review(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Approved,
        ]);

        $this->actingAs($faculty);

        $returnCase = app(ReturnCase::class);

        $this->expectException(HttpException::class);
        $returnCase($faculty, $case, 'Too late.', [
            ['section' => 'soap', 'body' => 'x', 'is_flagged' => true],
        ]);
    }

    public function test_a_failed_return_leaves_no_transition_or_comment_behind(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
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

        $returnCase = app(ReturnCase::class);

        // Two entries for the same section violate the DB unique constraint
        // on (case_status_transition_id, section) — this is what a bug in
        // the controller's own duplicate-section check (Task 7) would let
        // through, and the action must still roll back cleanly if it does.
        try {
            $returnCase($faculty, $case, 'Duplicate section.', [
                ['section' => 'soap', 'body' => 'First.', 'is_flagged' => true],
                ['section' => 'soap', 'body' => 'Second.', 'is_flagged' => false],
            ]);
            $this->fail('Expected a database exception for the duplicate section.');
        } catch (QueryException) {
            // expected
        }

        $this->assertDatabaseMissing('case_status_transitions', [
            'clinical_case_id' => $case->id,
            'to_status' => CaseStatus::Returned->value,
        ]);
        $this->assertDatabaseMissing('case_review_comments', [
            'clinical_case_id' => $case->id,
        ]);
        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
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

    public function test_reopen_http_endpoint_moves_an_approved_case_back_to_returned(): void
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

        CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'original']],
            'snapshot_hash' => hash('sha256', 'original'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
            'approved_by' => $faculty->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($faculty);

        $this->post(route('faculty.reviews.reopen', $case), ['reason' => 'Please re-check the dosing.'])
            ->assertRedirect();

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Returned->value]);
    }

    public function test_return_http_endpoint_requires_at_least_one_flagged_section_comment(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
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

        $this->post(route('faculty.reviews.return', $case), [
            'reason' => 'Needs work.',
            'section_comments' => [
                ['section' => 'soap', 'body' => 'Fine as-is.', 'is_flagged' => false],
            ],
        ])->assertSessionHasErrors('section_comments');

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
    }

    public function test_return_http_endpoint_rejects_a_duplicate_section_in_the_request(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
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

        $this->post(route('faculty.reviews.return', $case), [
            'reason' => 'Needs work.',
            'section_comments' => [
                ['section' => 'soap', 'body' => 'First.', 'is_flagged' => true],
                ['section' => 'soap', 'body' => 'Second.', 'is_flagged' => false],
            ],
        ])->assertSessionHasErrors('section_comments');
    }

    public function test_return_http_endpoint_succeeds_with_a_valid_flagged_section_comment(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
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

        $this->post(route('faculty.reviews.return', $case), [
            'reason' => 'Needs work.',
            'section_comments' => [
                ['section' => 'soap', 'body' => 'Expand the assessment.', 'is_flagged' => true],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Returned->value]);
    }

    public function test_review_show_page_exposes_the_full_comment_history(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
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

        $this->post(route('faculty.reviews.return', $case), [
            'reason' => 'Needs work.',
            'section_comments' => [
                ['section' => 'soap', 'body' => 'Expand the assessment.', 'is_flagged' => true],
            ],
        ]);

        $response = $this->get(route('faculty.reviews.show', $case));
        $response->assertInertia(fn ($page) => $page
            ->where('clinicalCase.versions.0.status_transitions.0.review_comments.0.body', 'Expand the assessment.')
            ->where('clinicalCase.versions.0.status_transitions.0.review_comments.0.author.id', $faculty->id));
    }

    public function test_student_case_show_exposes_feedback_history_even_after_approval(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Approved,
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
            'approved_by' => $faculty->id,
            'approved_at' => now(),
        ]);

        $transition = CaseStatusTransition::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Submitted->value,
            'to_status' => CaseStatus::Approved->value,
            'actor_id' => $faculty->id,
            'case_version_id' => $version->id,
            'reason' => 'Well documented.',
        ]);

        CaseReviewComment::query()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'case_status_transition_id' => $transition->id,
            'section' => CaseReviewSection::Soap,
            'body' => 'Nicely reasoned.',
            'is_flagged' => false,
            'created_by' => $faculty->id,
        ]);

        $this->actingAs($student);

        $response = $this->get(route('student.cases.show', $case));
        $response->assertInertia(fn ($page) => $page
            ->where('clinicalCase.versions.0.status_transitions.0.review_comments.0.body', 'Nicely reasoned.'));
    }

    public function test_editor_shows_flagged_sections_from_the_latest_return_round_only(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Returned,
        ]);

        $version = CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now()->subDays(2),
        ]);

        // Round 1 (older): flags case_profile — must NOT appear as current.
        // `created_at` isn't mass-assignable on CaseStatusTransition, so it's
        // backdated afterward via forceFill to control ordering deterministically.
        $oldTransition = CaseStatusTransition::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Submitted->value,
            'to_status' => CaseStatus::Returned->value,
            'actor_id' => $faculty->id,
            'case_version_id' => $version->id,
            'reason' => 'First round.',
        ]);
        $oldTransition->forceFill(['created_at' => now()->subDay()])->save();
        CaseReviewComment::query()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'case_status_transition_id' => $oldTransition->id,
            'section' => CaseReviewSection::CaseProfile,
            'body' => 'Old round.',
            'is_flagged' => true,
            'created_by' => $faculty->id,
        ]);

        // Round 2 (latest): flags soap — this IS the current round.
        $newTransition = CaseStatusTransition::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Submitted->value,
            'to_status' => CaseStatus::Returned->value,
            'actor_id' => $faculty->id,
            'case_version_id' => $version->id,
            'reason' => 'Second round.',
            'created_at' => now(),
        ]);
        CaseReviewComment::query()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'case_status_transition_id' => $newTransition->id,
            'section' => CaseReviewSection::Soap,
            'body' => 'Latest round.',
            'is_flagged' => true,
            'created_by' => $faculty->id,
        ]);

        $presenter = app(ClinicalCasePresenter::class);
        $feedback = $presenter->reviewFeedback($case);

        $this->assertSame(['soap'], $feedback['flaggedSections']);
        $this->assertNull($feedback['reopenedReason']);
    }

    public function test_editor_shows_the_reopen_reason_and_no_flags_after_a_reopen(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Returned,
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
            'approved_by' => $faculty->id,
            'approved_at' => now(),
        ]);

        CaseStatusTransition::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Approved->value,
            'to_status' => CaseStatus::Returned->value,
            'actor_id' => $faculty->id,
            'case_version_id' => $version->id,
            'reason' => 'New labs changed the diagnosis.',
        ]);

        $presenter = app(ClinicalCasePresenter::class);
        $feedback = $presenter->reviewFeedback($case);

        $this->assertSame([], $feedback['flaggedSections']);
        $this->assertSame('New labs changed the diagnosis.', $feedback['reopenedReason']);
    }

    public function test_editor_review_feedback_is_empty_for_a_returned_case_with_no_transition_row(): void
    {
        // Backward compatibility: a case already Returned before this table
        // existed has no matching CaseStatusTransition/CaseReviewComment rows.
        [$institution, $student, , $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Returned,
        ]);

        $presenter = app(ClinicalCasePresenter::class);
        $feedback = $presenter->reviewFeedback($case);

        $this->assertSame(['flaggedSections' => [], 'reopenedReason' => null], $feedback);
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
