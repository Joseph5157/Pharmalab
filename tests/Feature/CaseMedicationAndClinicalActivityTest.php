<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\ClinicalActivityType;
use App\Enums\MedicationStatus;
use App\Models\CaseClinicalActivity;
use App\Models\CaseMedication;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CaseMedicationAndClinicalActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_medication_or_activity_rows_exist_until_deliberately_saved(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $this->assertCount(0, $case->fresh()->medications);
        $this->assertCount(0, $case->fresh()->clinicalActivities);
    }

    public function test_medication_row_persists_with_its_status_enum_value(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'medication_context' => 'chart',
            'generic_name' => 'Paracetamol',
            'dose_amount' => '500',
            'dose_unit' => 'mg',
            'route' => 'oral',
            'frequency' => 'TID',
            'status' => MedicationStatus::Active->value,
            'recorded_by' => $student->id,
        ]);

        $fresh = $medication->fresh();
        $this->assertSame(MedicationStatus::Active, $fresh->status);
        $this->assertTrue($case->fresh()->medications->first()->is($medication));
        $this->assertTrue(Gate::forUser($student)->allows('view', $medication));
    }

    public function test_clinical_activity_stores_a_typed_json_details_payload(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $activity = CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Adr->value,
            'status' => 'suspected_adr_yes',
            'details' => ['event' => 'Rash', 'seriousness' => 'non_serious'],
            'recorded_by' => $student->id,
        ]);

        $fresh = $activity->fresh();
        $this->assertSame(['event' => 'Rash', 'seriousness' => 'non_serious'], $fresh->details);
        $this->assertTrue($case->fresh()->clinicalActivities->first()->is($activity));
        $this->assertTrue(Gate::forUser($student)->allows('view', $activity));
    }

    public function test_a_different_student_cannot_view_medications_or_activities(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);

        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'generic_name' => 'Amoxicillin',
            'status' => MedicationStatus::Active->value,
            'recorded_by' => $student->id,
        ]);
        $activity = CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Counselling->value,
            'status' => 'performed',
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $medication));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $activity));
    }

    public function test_a_user_from_another_institution_cannot_view_medications_or_activities(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);

        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'generic_name' => 'Ibuprofen',
            'status' => MedicationStatus::Active->value,
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $medication));
    }

    public function test_student_cannot_update_medications_or_activities_once_the_case_is_submitted(): void
    {
        [$institution, $student, $case] = $this->makeCase(CaseStatus::Submitted);

        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'generic_name' => 'Metformin',
            'status' => MedicationStatus::Active->value,
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($student)->allows('update', $medication));
    }

    public function test_an_administrator_cannot_view_medications_or_activities(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $administrator = User::factory()->administrator()->create(['institution_id' => $institution->id]);

        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'generic_name' => 'Losartan',
            'status' => MedicationStatus::Active->value,
            'recorded_by' => $student->id,
        ]);
        $activity = CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Monitoring->value,
            'status' => 'follow_up_required',
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($administrator)->allows('view', $medication));
        $this->assertFalse(Gate::forUser($administrator)->allows('view', $activity));
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
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
        ]);

        return [$institution, $student, $case];
    }
}
