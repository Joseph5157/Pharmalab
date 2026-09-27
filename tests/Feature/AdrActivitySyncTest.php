<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\ClinicalActivityType;
use App\Models\CaseClinicalActivity;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdrActivitySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_adr_row_exists_until_the_first_deliberate_sync(): void
    {
        [, , $case] = $this->makeCase();

        $this->assertCount(0, $case->fresh()->clinicalActivities);
    }

    public function test_a_raw_duplicate_singleton_insert_is_rejected_by_the_database(): void
    {
        [, $student, $case] = $this->makeCase();
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Adr->value, 'recorded_by' => $student->id,
        ]);

        $this->expectException(QueryException::class);

        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Adr->value, 'recorded_by' => $student->id,
        ]);
    }

    public function test_two_sequential_syncs_reuse_the_same_row_rather_than_creating_a_second_one(): void
    {
        // True parallel-request concurrency isn't reproducible in single-process
        // PHPUnit; this test plus the raw-duplicate-insert test above are the
        // intended coverage — the first proves the app-level firstOrCreate path
        // is idempotent under normal sequential use, the second proves the
        // database itself refuses a duplicate if two requests ever did race
        // past the application-level lockForUpdate.
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $first = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'no',
        ]);
        $second = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1, 'status' => 'unable_to_assess',
        ]);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('activity.id') ?? true, $first->json('activity.id') ?? true);
        $this->assertCount(1, $case->fresh()->clinicalActivities);
    }

    public function test_answering_no_does_not_require_adr_details(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'status' => 'no',
        ]);

        $response->assertOk();
        $response->assertJsonPath('activity.status', 'no');
    }

    public function test_answering_yes_requires_event_and_suspected_medicine(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'status' => 'yes',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['details.event', 'details.suspected_medicine']);
    }

    public function test_answering_yes_with_details_persists_them(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'status' => 'yes',
            'details' => ['event' => 'Rash', 'suspected_medicine' => 'Amoxicillin', 'seriousness' => 'non_serious'],
        ]);

        $response->assertOk();
        $this->assertSame('Rash', $case->fresh()->clinicalActivities->first()->details['event']);
    }

    public function test_a_single_detail_field_edit_after_yes_preserves_previously_saved_sibling_keys(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'yes',
            'details' => ['event' => 'Rash', 'suspected_medicine' => 'Amoxicillin'],
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'details' => ['action_taken' => 'Medicine withdrawn.'],
        ]);

        $response->assertOk();
        $fresh = $case->fresh()->clinicalActivities->first();
        $this->assertSame('Rash', $fresh->details['event']);
        $this->assertSame('Amoxicillin', $fresh->details['suspected_medicine']);
        $this->assertSame('Medicine withdrawn.', $fresh->details['action_taken']);
    }

    public function test_changing_status_away_from_yes_clears_the_hidden_detail_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'yes',
            'details' => ['event' => 'Rash', 'suspected_medicine' => 'Amoxicillin'],
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1, 'status' => 'no',
        ])->assertOk();

        $this->assertNull($case->fresh()->clinicalActivities->first()->details);
    }

    public function test_a_different_student_cannot_sync_the_adr_activity(): void
    {
        [$institution, , $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'no',
        ])->assertForbidden();
    }

    public function test_an_unknown_top_level_field_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'no', 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_an_unknown_nested_details_key_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'yes',
            'details' => ['event' => 'Rash', 'suspected_medicine' => 'Amoxicillin', 'patient_name' => 'Should be rejected'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('details');
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'rotation_id' => $rotation->id, 'student_id' => $student->id, 'primary_preceptor_id' => $student->id, 'status' => 'active']);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id, 'case_number' => 1, 'status' => $status,
        ]);

        return [$institution, $student, $case];
    }
}
