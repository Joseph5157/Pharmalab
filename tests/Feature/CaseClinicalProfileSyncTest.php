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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CaseClinicalProfileSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_profile_row_exists_until_the_first_deliberate_sync(): void
    {
        [, , $case] = $this->makeCase();

        $this->assertNull($case->fresh()->clinicalProfile);
    }

    public function test_first_sync_lazily_creates_the_profile_and_persists_fields(): void
    {
        [, $student, $case] = $this->makeCase();

        $this->syncAs($student, $case, [
            'history_present_illness' => 'Three-day history of headache.',
            'allergy_status' => 'no_known_allergy',
        ])->assertOk()
            ->assertJsonPath('section.allergy_status', 'no_known_allergy')
            ->assertJsonPath('section.lock_version', 1);

        $this->assertSame('Three-day history of headache.', $case->fresh()->clinicalProfile?->history_present_illness);
    }

    public function test_a_single_field_payload_does_not_require_or_erase_allergy_data(): void
    {
        [, $student, $case] = $this->makeCase();

        $this->syncAs($student, $case, [
            'allergy_status' => 'known_allergy',
            'allergy_substance' => 'Penicillin',
        ])->assertOk();
        $this->syncAs($student, $case, ['past_medical_history' => 'Type 2 diabetes mellitus, 5 years.'], 1)->assertOk();

        $profile = $case->fresh()->clinicalProfile;
        $this->assertSame('known_allergy', $profile?->allergy_status);
        $this->assertSame('Penicillin', $profile?->allergy_substance);
        $this->assertSame('Type 2 diabetes mellitus, 5 years.', $profile?->past_medical_history);
    }

    public function test_previously_missing_history_fields_can_be_saved_in_one_partial_payload(): void
    {
        [, $student, $case] = $this->makeCase();

        $this->syncAs($student, $case, [
            'past_surgical_history' => 'Appendicectomy, 2019.',
            'adherence_status' => 'adherent',
            'family_history' => 'Father: hypertension.',
            'substance_history' => 'No tobacco or alcohol use reported.',
            'examination_findings' => 'Alert, oriented, no acute distress.',
        ])->assertOk();

        $profile = $case->fresh()->clinicalProfile;
        $this->assertSame('Appendicectomy, 2019.', $profile?->past_surgical_history);
        $this->assertSame('adherent', $profile?->adherence_status);
        $this->assertSame('Father: hypertension.', $profile?->family_history);
        $this->assertSame('No tobacco or alcohol use reported.', $profile?->substance_history);
        $this->assertSame('Alert, oriented, no acute distress.', $profile?->examination_findings);
    }

    public function test_allergy_substance_is_required_with_known_allergy_in_the_same_request(): void
    {
        [, $student, $case] = $this->makeCase();

        $this->syncAs($student, $case, ['allergy_status' => 'known_allergy'])->assertUnprocessable()
            ->assertJsonValidationErrors('allergy_substance');
    }

    public function test_changing_allergy_status_clears_stale_conditional_fields(): void
    {
        [, $student, $case] = $this->makeCase();

        $this->syncAs($student, $case, [
            'allergy_status' => 'known_allergy',
            'allergy_substance' => 'Penicillin',
            'allergy_reaction' => 'Rash',
        ])->assertOk();
        $this->syncAs($student, $case, ['allergy_status' => 'no_known_allergy'], 1)->assertOk();

        $profile = $case->fresh()->clinicalProfile;
        $this->assertSame('no_known_allergy', $profile?->allergy_status);
        $this->assertNull($profile?->allergy_substance);
        $this->assertNull($profile?->allergy_reaction);
    }

    public function test_rendering_a_case_page_never_creates_a_profile_row(): void
    {
        [, $student, $case] = $this->makeCase();

        $this->actingAs($student)->get(route('student.cases.show', $case))->assertOk();

        $this->assertNull($case->fresh()->clinicalProfile);
    }

    public function test_unknown_fields_are_rejected(): void
    {
        [, $student, $case] = $this->makeCase();

        $this->syncAs($student, $case, ['allergy_status' => 'unknown', 'patient_mrn' => '12345'])->assertUnprocessable()
            ->assertJsonValidationErrors('patient_mrn');
    }

    public function test_a_different_student_cannot_sync_a_clinical_profile(): void
    {
        [$institution, , $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);

        $this->syncAs($otherStudent, $case, ['allergy_status' => 'unknown'])->assertForbidden();
    }

    /** @param array<string, mixed> $attributes */
    private function syncAs(User $student, ClinicalCase $case, array $attributes, int $baseLockVersion = 0): TestResponse
    {
        return $this->actingAs($student)->putJson(route('student.cases.clinical-profile.sync', $case), [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => $baseLockVersion,
            ...$attributes,
        ]);
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(): array
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
        ])];
    }
}
