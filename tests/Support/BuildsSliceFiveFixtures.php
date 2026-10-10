<?php

namespace Tests\Support;

use App\Actions\ApproveCase;
use App\Actions\SubmitCase;
use App\Enums\CaseStatus;
use App\Models\CaseClinicalActivity;
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
use App\Models\RotationCaseTarget;
use App\Models\SoapNote;
use App\Models\User;
use App\Models\Ward;

/**
 * Shared fixtures for the Slice 5 tests (targets, progress, export). Every
 * row is created without global scopes and with an explicit institution, so
 * cross-tenant fixtures are as easy to build as same-tenant ones.
 */
trait BuildsSliceFiveFixtures
{
    private int $caseNumberSeed = 0;

    protected function makeRotation(?Institution $institution = null, string $name = 'General Medicine'): Rotation
    {
        $institution ??= Institution::factory()->create();
        $suffix = substr(md5($name.microtime()), 0, 6);
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'City Hospital', 'code' => 'CH'.$suffix, 'status' => 'active']);
        $ward = Ward::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'clinical_site_id' => $site->id, 'name' => 'Ward 3', 'code' => 'W'.$suffix, 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD'.$suffix, 'duration_years' => 6, 'status' => 'active']);

        return Rotation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'programme_id' => $programme->id,
            'clinical_site_id' => $site->id,
            'ward_id' => $ward->id,
            'name' => $name,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
            'status' => 'active',
        ]);
    }

    protected function institutionOf(Rotation $rotation): Institution
    {
        return Institution::query()->findOrFail($rotation->institution_id);
    }

    protected function makeStudent(Rotation|Institution $scope, string $name = 'Asha Student'): User
    {
        return User::factory()->student()->create(['institution_id' => $this->institutionId($scope), 'name' => $name]);
    }

    protected function makeFaculty(Rotation|Institution $scope, string $name = 'Dr Ravi Preceptor'): User
    {
        return User::factory()->faculty()->create(['institution_id' => $this->institutionId($scope), 'name' => $name]);
    }

    protected function makeAdministrator(Rotation|Institution $scope): User
    {
        return User::factory()->administrator()->create(['institution_id' => $this->institutionId($scope)]);
    }

    protected function assign(Rotation $rotation, User $student, User $faculty, string $status = 'active'): RotationAssignment
    {
        return RotationAssignment::query()->withoutGlobalScopes()->create([
            'institution_id' => $rotation->institution_id,
            'rotation_id' => $rotation->id,
            'student_id' => $student->id,
            'primary_preceptor_id' => $faculty->id,
            'status' => $status,
        ]);
    }

    protected function addTarget(Rotation $rotation, ?string $category, int $count, User $faculty): RotationCaseTarget
    {
        return RotationCaseTarget::query()->withoutGlobalScopes()->create([
            'institution_id' => $rotation->institution_id,
            'rotation_id' => $rotation->id,
            'category' => $category,
            'target_count' => $count,
            'created_by' => $faculty->id,
        ]);
    }

    /** A bare case row with the given status; for counting tests that need no versions. */
    protected function makeCase(RotationAssignment $assignment, CaseStatus $status, ?string $category = null): ClinicalCase
    {
        return ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $assignment->institution_id,
            'student_id' => $assignment->student_id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => ++$this->caseNumberSeed,
            'status' => $status,
            'form_version' => 'pharmd-case-v1',
            'case_category' => $category,
            'approved_at' => $status === CaseStatus::Approved ? now() : null,
        ]);
    }

    /** A case that passes every completeness check, submitted and approved through the real actions. */
    protected function makeApprovedCase(RotationAssignment $assignment, ?string $category = 'Cardiology'): ClinicalCase
    {
        $case = $this->makeCompleteDraftCase($assignment, $category);
        $student = User::query()->findOrFail($assignment->student_id);
        $faculty = User::query()->findOrFail($assignment->primary_preceptor_id);

        $this->actingAs($student);
        app(SubmitCase::class)($student, $case, true);
        $this->actingAs($faculty);
        app(ApproveCase::class)($faculty, $case->fresh());

        return $case->fresh();
    }

    protected function makeCompleteDraftCase(RotationAssignment $assignment, ?string $category = 'Cardiology'): ClinicalCase
    {
        $rotation = Rotation::query()->withoutGlobalScopes()->findOrFail($assignment->rotation_id);
        $institutionId = $assignment->institution_id;
        $studentId = $assignment->student_id;

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institutionId,
            'student_id' => $studentId,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => ++$this->caseNumberSeed,
            'status' => CaseStatus::Draft,
            'form_version' => 'pharmd-case-v1',
            'encounter_date' => '2026-10-05',
            'case_category' => $category,
            'clinical_site_id' => $rotation->clinical_site_id,
            'ward_id' => $rotation->ward_id,
            'care_setting' => 'inpatient',
            'information_source' => 'case sheet',
            'age_value' => 58,
            'age_unit' => 'years',
            'sex' => 'male',
            'vitals_status' => 'recorded',
            'investigations_status' => 'recorded',
            'medication_chart_status' => 'documented',
        ]);

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institutionId, 'clinical_case_id' => $case->id,
            'chief_complaints' => [['complaint' => 'Breathlessness on exertion', 'duration' => '2 weeks']],
            'history_present_illness' => 'Progressive exertional dyspnoea with ankle swelling.',
            'diagnoses' => [['label' => 'Congestive heart failure', 'type' => 'provisional']],
            'past_medical_history_none' => true,
            'allergy_status' => 'no_known_allergy',
            'last_saved_by' => $studentId,
        ]);
        CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institutionId, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 96, 'recorded_by' => $studentId,
        ]);
        CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institutionId, 'clinical_case_id' => $case->id,
            'test_name' => 'Serum creatinine', 'result_type' => 'numeric', 'result_value' => '1.4',
            'unit' => 'mg/dL', 'reference_range_not_provided' => true, 'recorded_by' => $studentId,
        ]);
        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institutionId, 'clinical_case_id' => $case->id,
            'medication_context' => null, 'generic_name' => 'Furosemide', 'indication' => 'Fluid overload',
            'dose_amount' => '40', 'dose_unit' => 'mg', 'route' => 'oral', 'frequency' => 'OD',
            'status' => 'active', 'recorded_by' => $studentId,
        ]);
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institutionId, 'clinical_case_id' => $case->id,
            'activity_type' => 'adr', 'status' => 'no', 'recorded_by' => $studentId,
        ]);
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institutionId, 'clinical_case_id' => $case->id,
            'activity_type' => 'counselling', 'status' => 'not_indicated', 'recorded_by' => $studentId,
        ]);
        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institutionId, 'clinical_case_id' => $case->id, 'revision_number' => 1,
            'subjective' => 'Reports breathlessness climbing one flight of stairs.',
            'objective' => 'Bilateral pitting oedema; pulse 96.',
            'assessment' => 'Decompensated heart failure.',
            'plan' => 'Continue loop diuretic; daily weights.',
            'monitoring_plan' => 'Monitor potassium and creatinine every 48 hours.',
            'drug_related_problem_status' => 'none_identified',
            'author_id' => $studentId, 'last_saved_by' => $studentId,
        ]);

        return $case;
    }

    private function institutionId(Rotation|Institution $scope): string
    {
        return $scope instanceof Rotation ? $scope->institution_id : $scope->id;
    }
}
