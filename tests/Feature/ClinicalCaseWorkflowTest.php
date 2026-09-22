<?php

namespace Tests\Feature;

use App\Actions\ApproveCase;
use App\Actions\ReturnCase;
use App\Actions\SubmitCase;
use App\Enums\CaseStatus;
use App\Models\CaseVersion;
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
            'encounter_date' => '2026-10-15',
            'case_category' => 'Drug Therapy Problem',
            'clinical_site_id' => $site->id,
        ]);

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Draft->value]);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'Patient reports headache for 3 days.',
            'objective' => 'BP 140/90, HR 80.',
            'assessment' => 'Tension-type headache.',
            'plan' => 'Recommend paracetamol 500mg TID.',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        $submitCase = app(SubmitCase::class);
        $version = $submitCase($student, $case);

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
        ]);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'Updated subjective.',
            'objective' => 'Updated objective.',
            'assessment' => 'Updated assessment with more detail.',
            'plan' => 'Updated plan.',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        $this->actingAs($student);

        $submitCase = app(SubmitCase::class);
        $version = $submitCase($student, $case);

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
        $this->assertDatabaseHas('case_versions', ['clinical_case_id' => $case->id, 'version_number' => 2]);
    }
}
