<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseClinicalProfile;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CaseClinicalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_case_has_no_profile_until_one_is_deliberately_created(): void
    {
        [$institution, $student, $faculty, $case] = $this->makeCase();

        $this->assertNull($case->fresh()->clinicalProfile);
    }

    public function test_profile_can_be_created_and_belongs_to_the_case(): void
    {
        [$institution, $student, $faculty, $case] = $this->makeCase();

        $profile = CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'history_present_illness' => 'Three-day history of headache.',
            'diagnoses' => [['label' => 'Tension-type headache', 'type' => 'provisional']],
            'allergy_status' => 'no_known_allergy',
            'last_saved_by' => $student->id,
        ]);

        $this->assertTrue($case->fresh()->clinicalProfile->is($profile));
        $this->assertSame('Three-day history of headache.', $profile->fresh()->history_present_illness);
        $this->assertSame([['label' => 'Tension-type headache', 'type' => 'provisional']], $profile->fresh()->diagnoses);
    }

    public function test_allergy_status_is_null_when_not_explicitly_set_rather_than_defaulting_to_unknown(): void
    {
        [$institution, $student, $faculty, $case] = $this->makeCase();

        $profile = CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'last_saved_by' => $student->id,
        ]);

        $this->assertNull($profile->fresh()->allergy_status);
    }

    public function test_duplicate_profile_for_the_same_case_is_rejected_by_the_database(): void
    {
        [$institution, $student, $faculty, $case] = $this->makeCase();

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'allergy_status' => 'unknown',
            'last_saved_by' => $student->id,
        ]);

        $this->expectException(QueryException::class);

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'allergy_status' => 'unknown',
            'last_saved_by' => $student->id,
        ]);
    }

    public function test_owning_student_may_view_and_update_their_draft_profile(): void
    {
        [$institution, $student, $faculty, $case, $profile] = $this->makeCaseWithProfile();

        $this->assertTrue(Gate::forUser($student)->allows('view', $profile));
        $this->assertTrue(Gate::forUser($student)->allows('update', $profile));
    }

    public function test_a_different_student_cannot_view_the_profile(): void
    {
        [$institution, $student, $faculty, $case, $profile] = $this->makeCaseWithProfile();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $profile));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('update', $profile));
    }

    public function test_a_user_from_another_institution_cannot_view_the_profile(): void
    {
        [$institution, $student, $faculty, $case, $profile] = $this->makeCaseWithProfile();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $profile));
    }

    public function test_the_institution_scope_hides_the_profile_from_a_user_in_another_institution(): void
    {
        [$institution, $student, $faculty, $case, $profile] = $this->makeCaseWithProfile();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);

        $this->actingAs($otherStudent);

        $this->assertNull(CaseClinicalProfile::query()->find($profile->id));
    }

    public function test_a_profile_row_whose_institution_does_not_match_the_case_is_denied_even_to_the_owning_student(): void
    {
        [$institution, $student, $faculty, $case, $profile] = $this->makeCaseWithProfile();
        $otherInstitution = Institution::factory()->create();

        $profile->forceFill(['institution_id' => $otherInstitution->id])->saveQuietly();

        $this->assertFalse(Gate::forUser($student)->allows('view', $profile->fresh()));
    }

    public function test_the_assigned_preceptor_may_view_but_not_update_the_profile(): void
    {
        [$institution, $student, $faculty, $case, $profile] = $this->makeCaseWithProfile();

        $this->assertTrue(Gate::forUser($faculty)->allows('view', $profile));
        $this->assertFalse(Gate::forUser($faculty)->allows('update', $profile));
    }

    public function test_a_non_assigned_faculty_member_in_the_same_institution_cannot_view_the_profile(): void
    {
        [$institution, $student, $faculty, $case, $profile] = $this->makeCaseWithProfile();
        $otherFaculty = User::factory()->faculty()->create(['institution_id' => $institution->id]);

        $this->assertFalse(Gate::forUser($otherFaculty)->allows('view', $profile));
    }

    public function test_student_cannot_update_the_profile_once_the_case_is_submitted(): void
    {
        [$institution, $student, $faculty, $case, $profile] = $this->makeCaseWithProfile(CaseStatus::Submitted);

        $this->assertFalse(Gate::forUser($student)->allows('update', $profile));
    }

    public function test_an_administrator_cannot_view_or_update_the_profile(): void
    {
        [$institution, $student, $faculty, $case, $profile] = $this->makeCaseWithProfile();
        $administrator = User::factory()->administrator()->create(['institution_id' => $institution->id]);

        $this->assertFalse(Gate::forUser($administrator)->allows('view', $profile));
        $this->assertFalse(Gate::forUser($administrator)->allows('update', $profile));
    }

    /** @return array{Institution, User, User, ClinicalCase} */
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

        return [$institution, $student, $faculty, $case];
    }

    /** @return array{Institution, User, User, ClinicalCase, CaseClinicalProfile} */
    private function makeCaseWithProfile(CaseStatus $status = CaseStatus::Draft): array
    {
        [$institution, $student, $faculty, $case] = $this->makeCase($status);

        $profile = CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'allergy_status' => 'unknown',
            'last_saved_by' => $student->id,
        ]);

        return [$institution, $student, $faculty, $case, $profile];
    }
}
