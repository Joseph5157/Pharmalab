<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseClinicalProfile;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\ClinicalSite;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PharmdCaseSectionAvailabilityFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinical_cases_has_the_explicit_absence_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('clinical_cases', ['vitals_status', 'vitals_unavailable_reason', 'investigations_status', 'investigations_unavailable_reason', 'medication_chart_status', 'medication_chart_none_reason']));
    }

    public function test_clinical_cases_has_independent_lock_columns_for_the_three_availability_toggles(): void
    {
        $this->assertTrue(Schema::hasColumns('clinical_cases', ['vitals_availability_lock_version', 'investigations_availability_lock_version', 'medication_chart_availability_lock_version']));
    }

    public function test_explicit_absence_fields_persist_on_a_case(): void
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = $this->makeCase($institution, $student, ['vitals_status' => 'unavailable', 'vitals_unavailable_reason' => 'Patient not examined.', 'investigations_status' => 'recorded', 'medication_chart_status' => 'none_documented', 'medication_chart_none_reason' => 'No medicines reported.']);

        $fresh = $case->fresh();
        $this->assertSame('unavailable', $fresh->vitals_status);
        $this->assertSame('Patient not examined.', $fresh->vitals_unavailable_reason);
        $this->assertSame('recorded', $fresh->investigations_status);
        $this->assertSame('none_documented', $fresh->medication_chart_status);
        $this->assertSame('No medicines reported.', $fresh->medication_chart_none_reason);
        $this->assertSame(0, $fresh->vitals_availability_lock_version);
        $this->assertSame(0, $fresh->investigations_availability_lock_version);
        $this->assertSame(0, $fresh->medication_chart_availability_lock_version);
    }

    public function test_lock_version_is_not_mass_assignable_on_case_clinical_profile(): void
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = $this->makeCase($institution, $student);
        $profile = CaseClinicalProfile::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'clinical_case_id' => $case->id, 'allergy_status' => 'unknown', 'last_saved_by' => $student->id, 'lock_version' => 99]);

        $this->assertSame(0, $profile->fresh()->lock_version);
    }

    /** @param array<string, mixed> $attributes */
    private function makeCase(Institution $institution, User $student, array $attributes = []): ClinicalCase
    {
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'rotation_id' => $rotation->id, 'student_id' => $student->id, 'primary_preceptor_id' => $student->id, 'status' => 'active']);

        return ClinicalCase::query()->withoutGlobalScopes()->create(array_merge(['institution_id' => $institution->id, 'student_id' => $student->id, 'rotation_assignment_id' => $assignment->id, 'case_number' => 1, 'status' => CaseStatus::Draft], $attributes));
    }
}
