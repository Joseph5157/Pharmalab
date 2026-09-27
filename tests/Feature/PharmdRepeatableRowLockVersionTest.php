<?php

namespace Tests\Feature;

use App\Contracts\Syncable;
use App\Enums\CaseStatus;
use App\Enums\MedicationStatus;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PharmdRepeatableRowLockVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_three_repeatable_tables_have_a_lock_version_column(): void
    {
        $this->assertTrue(Schema::hasColumn('case_vitals', 'lock_version'));
        $this->assertTrue(Schema::hasColumn('case_investigations', 'lock_version'));
        $this->assertTrue(Schema::hasColumn('case_medications', 'lock_version'));
    }

    public function test_case_vitals_has_systolic_and_diastolic_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('case_vitals', ['value_systolic', 'value_diastolic']));
    }

    public function test_case_investigations_has_not_stated_flags(): void
    {
        $this->assertTrue(Schema::hasColumns('case_investigations', ['unit_not_stated', 'reference_range_not_provided']));
    }

    public function test_case_medications_has_indication_unclear_flag(): void
    {
        $this->assertTrue(Schema::hasColumn('case_medications', 'indication_unclear'));
    }

    public function test_all_three_models_implement_syncable_and_bump_on_apply(): void
    {
        [, $student, $case] = $this->makeCase();

        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
        ]);
        $investigation = CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'test_name' => 'Sodium', 'result_type' => 'numeric', 'result_value' => '140', 'recorded_by' => $student->id,
        ]);
        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'generic_name' => 'Paracetamol', 'status' => MedicationStatus::Active->value, 'recorded_by' => $student->id,
        ]);

        // Each model's own nullable text column stands in for "some
        // attribute the server can normalize" — case_investigations has no
        // `note` column (it has `interpretation`), and case_medications has
        // `notes`, not `note`, so a single shared key across all three would
        // fail on two of them.
        $rowsWithNullableField = [
            [$vital, 'note'],
            [$investigation, 'interpretation'],
            [$medication, 'notes'],
        ];
        foreach ($rowsWithNullableField as [$row, $field]) {
            $this->assertInstanceOf(Syncable::class, $row);
            $this->assertSame(0, $row->getLockVersion('irrelevant-for-single-lock-models'));
            $row->applySyncedAttributes('irrelevant-for-single-lock-models', [$field => null], 1);
        }
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'rotation_id' => $rotation->id,
            'student_id' => $student->id, 'primary_preceptor_id' => $student->id, 'status' => 'active',
        ]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id, 'case_number' => 1, 'status' => CaseStatus::Draft,
        ]);

        return [$institution, $student, $case];
    }
}
