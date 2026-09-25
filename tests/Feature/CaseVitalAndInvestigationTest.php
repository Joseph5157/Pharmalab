<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseInvestigation;
use App\Models\CaseVital;
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

class CaseVitalAndInvestigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_vital_row_is_only_created_when_deliberately_saved(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $this->assertCount(0, $case->fresh()->vitals);
    }

    public function test_vital_belongs_to_the_case_and_is_visible_to_the_owning_student(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'blood_pressure',
            'value_text' => '120/80',
            'unit' => 'mmHg',
            'observed_on' => '2026-10-15',
            'recorded_by' => $student->id,
        ]);

        $this->assertTrue($case->fresh()->vitals->first()->is($vital));
        $this->assertTrue(Gate::forUser($student)->allows('view', $vital));
    }

    public function test_investigation_belongs_to_the_case_and_is_visible_to_the_owning_student(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $investigation = CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'test_name' => 'Haemoglobin',
            'result_type' => 'numeric',
            'result_value' => '13.5',
            'unit' => 'g/dL',
            'recorded_by' => $student->id,
        ]);

        $this->assertTrue($case->fresh()->investigations->first()->is($investigation));
        $this->assertTrue(Gate::forUser($student)->allows('view', $investigation));
    }

    public function test_a_different_student_cannot_view_vitals_or_investigations(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);

        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'pulse',
            'value_numeric' => 80,
            'unit' => 'beats/min',
            'recorded_by' => $student->id,
        ]);
        $investigation = CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'test_name' => 'Sodium',
            'result_type' => 'numeric',
            'result_value' => '140',
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $vital));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $investigation));
    }

    public function test_a_user_from_another_institution_cannot_view_vitals_or_investigations(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);

        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'temperature',
            'value_numeric' => 37,
            'unit' => '°C',
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $vital));
    }

    public function test_student_cannot_update_vitals_once_the_case_is_submitted(): void
    {
        [$institution, $student, $case] = $this->makeCase(CaseStatus::Submitted);

        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'spo2',
            'value_numeric' => 98,
            'unit' => '%',
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($student)->allows('update', $vital));
    }

    public function test_an_administrator_cannot_view_vitals_or_investigations(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $administrator = User::factory()->administrator()->create(['institution_id' => $institution->id]);

        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'pulse',
            'value_numeric' => 80,
            'unit' => 'beats/min',
            'recorded_by' => $student->id,
        ]);
        $investigation = CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'test_name' => 'Sodium',
            'result_type' => 'numeric',
            'result_value' => '140',
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($administrator)->allows('view', $vital));
        $this->assertFalse(Gate::forUser($administrator)->allows('view', $investigation));
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
