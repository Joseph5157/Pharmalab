<?php

namespace Tests\Unit\Services;

use App\Enums\CaseStatus;
use App\Models\CaseClinicalProfile;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\SoapNote;
use App\Models\User;
use App\Services\CaseCompletenessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseCompletenessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_brand_new_case_is_missing_every_section(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $service = new CaseCompletenessService;

        $this->assertSame([
            'case_context' => false,
            'history_and_diagnosis' => false,
            'vitals' => false,
            'investigations' => false,
            'medication_chart' => false,
            'soap' => false,
        ], $service->sectionCompletion($case->fresh()));
        $this->assertFalse($service->isReadyForSubmission($case->fresh()));
    }

    public function test_a_fully_documented_case_has_every_section_complete(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $case->update(['care_setting' => 'inpatient', 'age_value' => 34, 'sex' => 'female']);

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'history_present_illness' => 'Three-day headache.',
            'diagnoses' => [['label' => 'Tension headache']],
            'past_medical_history_none' => true,
            'allergy_status' => 'no_known_allergy',
            'last_saved_by' => $student->id,
        ]);

        CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'pulse',
            'value_numeric' => 80,
            'recorded_by' => $student->id,
        ]);

        CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'test_name' => 'Haemoglobin',
            'result_type' => 'numeric',
            'result_value' => '13.5',
            'recorded_by' => $student->id,
        ]);

        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'medication_context' => 'chart',
            'generic_name' => 'Paracetamol',
            'status' => 'active',
            'recorded_by' => $student->id,
        ]);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'S',
            'objective' => 'O',
            'assessment' => 'A',
            'plan' => 'P',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        $service = new CaseCompletenessService;
        $fresh = $case->fresh();

        $this->assertSame([
            'case_context' => true,
            'history_and_diagnosis' => true,
            'vitals' => true,
            'investigations' => true,
            'medication_chart' => true,
            'soap' => true,
        ], $service->sectionCompletion($fresh));
        $this->assertTrue($service->isReadyForSubmission($fresh));
        $this->assertSame([], $service->missingSections($fresh));
    }

    public function test_missing_sections_lists_only_the_incomplete_ones(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $case->update(['care_setting' => 'inpatient', 'age_value' => 34, 'sex' => 'female']);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'S',
            'objective' => 'O',
            'assessment' => 'A',
            'plan' => 'P',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        $service = new CaseCompletenessService;

        $this->assertSame(
            ['history_and_diagnosis', 'vitals', 'investigations', 'medication_chart'],
            $service->missingSections($case->fresh()),
        );
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(): array
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
            'status' => CaseStatus::Draft,
        ]);

        return [$institution, $student, $case];
    }
}
