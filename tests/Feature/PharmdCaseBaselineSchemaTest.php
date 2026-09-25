<?php

namespace Tests\Feature;

use App\Enums\CaseFormVersion;
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

class PharmdCaseBaselineSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_case_created_without_an_explicit_form_version_receives_the_fixed_default(): void
    {
        [$institution, $student, $assignment] = $this->makeAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
        ]);

        $this->assertSame(CaseFormVersion::PharmdV1, $case->fresh()->form_version);
    }

    public function test_case_context_and_attestation_fields_persist(): void
    {
        [$institution, $student, $assignment] = $this->makeAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
            'care_setting' => 'inpatient',
            'hospital_day_at_first_review' => 2,
            'information_source' => 'case sheet',
            'weight_kg' => 70.5,
            'height_cm' => 172.25,
            'pregnancy_lactation_status' => 'not_applicable',
        ]);

        $fresh = $case->fresh();
        $this->assertNull($fresh->deidentification_attested_at);
        $this->assertNull($fresh->deidentification_attested_by);
        $this->assertSame('inpatient', $fresh->care_setting);
        $this->assertSame(2, $fresh->hospital_day_at_first_review);
        $this->assertSame('70.50', $fresh->weight_kg);
        $this->assertSame('172.25', $fresh->height_cm);

        $fresh->update([
            'deidentification_attested_at' => now(),
            'deidentification_attested_by' => $student->id,
        ]);

        $this->assertTrue($fresh->fresh()->attestedBy->is($student));
    }

    public function test_soap_note_stores_drug_related_problem_status_and_categories(): void
    {
        [$institution, $student, $assignment] = $this->makeAssignment();
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
        ]);

        $soap = SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
            'drug_related_problem_status' => 'identified',
            'drug_related_problem_categories' => ['dose_too_low', 'monitoring_required'],
        ]);

        $fresh = $soap->fresh();
        $this->assertSame('identified', $fresh->drug_related_problem_status);
        $this->assertSame(['dose_too_low', 'monitoring_required'], $fresh->drug_related_problem_categories);
    }

    /** @return array{Institution, User, RotationAssignment} */
    private function makeAssignment(): array
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

        return [$institution, $student, $assignment];
    }
}
