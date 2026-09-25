<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaseContextSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owning_student_can_partially_autosave_case_context(): void
    {
        [, $student, $case] = $this->makeCase();

        $response = $this->syncAs($student, $case, [
            'case_category' => 'Medication reconciliation',
            'age_value' => 43,
            'age_unit' => 'years',
        ]);

        $response->assertOk()
            ->assertJsonPath('section.case_category', 'Medication reconciliation')
            ->assertJsonPath('section.age_value', 43)
            ->assertJsonPath('section.age_unit', 'years')
            ->assertJsonPath('section.case_display.case_number', 1)
            ->assertJsonPath('section.lock_version', 1);
    }

    public function test_partial_autosave_does_not_erase_omitted_fields(): void
    {
        [, $student, $case] = $this->makeCase([
            'case_category' => 'Initial assessment',
            'care_setting' => 'inpatient',
        ]);

        $this->syncAs($student, $case, ['information_source' => 'Patient'])->assertOk();

        $case->refresh();
        $this->assertSame('Initial assessment', $case->case_category);
        $this->assertSame('inpatient', $case->care_setting);
        $this->assertSame('Patient', $case->information_source);
    }

    public function test_unknown_case_context_fields_are_rejected(): void
    {
        [, $student, $case] = $this->makeCase();

        $this->syncAs($student, $case, ['not_a_case_context_field' => 'no'])->assertUnprocessable()
            ->assertJsonValidationErrors('not_a_case_context_field');
    }

    public function test_age_validation_respects_the_selected_unit(): void
    {
        [, $student, $case] = $this->makeCase();

        $this->syncAs($student, $case, ['age_value' => 365, 'age_unit' => 'days'])->assertUnprocessable()
            ->assertJsonValidationErrors('age_value');
        $this->syncAs($student, $case, ['age_value' => 59, 'age_unit' => 'months'])->assertOk();
        $this->syncAs($student, $case, ['age_value' => 121, 'age_unit' => 'years'])->assertUnprocessable()
            ->assertJsonValidationErrors('age_value');
    }

    public function test_case_profile_locking_is_independent_from_other_case_sections(): void
    {
        [, $student, $case] = $this->makeCase();

        app(\App\Services\SectionSyncService::class)->sync(
            $case,
            $student,
            'vitals_availability',
            (string) Str::uuid(),
            0,
            ['vitals_status' => 'unavailable'],
            null,
            false,
        );

        $this->syncAs($student, $case, ['case_category' => 'Independent lock'])->assertOk()
            ->assertJsonPath('section.lock_version', 1);

        $case->refresh();
        $this->assertSame(1, $case->lock_version);
        $this->assertSame(1, $case->vitals_availability_lock_version);
    }

    public function test_stale_case_context_lock_returns_the_server_snapshot(): void
    {
        [, $student, $case] = $this->makeCase();

        $this->syncAs($student, $case, ['case_category' => 'Server version'])->assertOk();

        $this->syncAs($student, $case, ['case_category' => 'Stale device version'], 0)->assertConflict()
            ->assertJsonPath('section.case_category', 'Server version')
            ->assertJsonPath('section.lock_version', 1);

        $this->assertSame('Server version', $case->fresh()->case_category);
    }

    public function test_replaying_an_operation_id_is_idempotent(): void
    {
        [, $student, $case] = $this->makeCase();
        $operationId = (string) Str::uuid();

        $this->syncAs($student, $case, ['case_category' => 'First value'], 0, $operationId)->assertOk();
        $this->syncAs($student, $case, ['case_category' => 'Replay value'], 0, $operationId)->assertOk();

        $case->refresh();
        $this->assertSame('First value', $case->case_category);
        $this->assertSame(1, $case->lock_version);
    }

    public function test_a_different_student_cannot_update_another_students_case(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);

        $this->syncAs($otherStudent, $case, ['case_category' => 'Unauthorized'])->assertForbidden();
        $this->assertNull($case->fresh()->case_category);
    }

    public function test_a_student_cannot_access_a_case_from_another_institution(): void
    {
        [, $student, $case] = $this->makeCase();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);

        $this->syncAs($otherStudent, $case, ['case_category' => 'Cross-institution'])->assertNotFound();
        $this->assertNull($case->fresh()->case_category);
    }

    /** @param array<string, mixed> $attributes */
    private function syncAs(User $student, ClinicalCase $case, array $attributes, int $baseLockVersion = 0, ?string $operationId = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($student)->putJson(route('student.cases.context.sync', $case), [
            'client_operation_id' => $operationId ?? (string) Str::uuid(),
            'base_lock_version' => $baseLockVersion,
            ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes @return array{Institution, User, ClinicalCase} */
    private function makeCase(array $attributes = []): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'rotation_id' => $rotation->id, 'student_id' => $student->id, 'primary_preceptor_id' => $student->id, 'status' => 'active']);

        return [$institution, $student, ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
            ...$attributes,
        ])];
    }
}
