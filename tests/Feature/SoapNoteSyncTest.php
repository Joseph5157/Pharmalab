<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\AuditEvent;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\SoapNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SoapNoteSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_sync_lazily_creates_the_soap_note_and_persists_all_fields_including_monitoring_plan(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'subjective' => 'Patient reports headache for 3 days.',
            'drug_related_problem_status' => 'identified',
            'drug_related_problem_categories' => ['dose_too_low', 'monitoring_required'],
            'monitoring_plan' => 'Blood pressure daily for 3 days.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('section.subjective', 'Patient reports headache for 3 days.');
        $this->assertNotNull($case->fresh()->currentSoap);
        $this->assertSame(['dose_too_low', 'monitoring_required'], $case->fresh()->currentSoap->drug_related_problem_categories);
        $this->assertSame('Blood pressure daily for 3 days.', $case->fresh()->currentSoap->monitoring_plan);
        $this->assertDatabaseHas('audit_events', [
            'auditable_type' => SoapNote::class,
            'event_type' => 'soap_note.created',
        ]);
    }

    public function test_a_second_sync_records_an_updated_audit_event_not_another_created_event(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'subjective' => 'First.',
        ])->assertOk();
        $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1, 'objective' => 'BP 120/80.',
        ])->assertOk();

        $this->assertSame(1, AuditEvent::query()->where('event_type', 'soap_note.created')->count());
        $this->assertSame(1, AuditEvent::query()->where('event_type', 'soap_note.updated')->count());
    }

    public function test_monitoring_plan_not_applicable_reason_can_be_recorded_instead_of_a_plan(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'monitoring_plan_not_applicable_reason' => 'Single-dose administration; no ongoing monitoring indicated.',
        ]);

        $response->assertOk();
        $this->assertSame('Single-dose administration; no ongoing monitoring indicated.', $case->fresh()->currentSoap->monitoring_plan_not_applicable_reason);
    }

    public function test_marking_monitoring_plan_not_applicable_persists_even_with_no_reason_typed_yet(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        // Regression for Task 9 live device verification: an empty reason
        // string is normalized to null by Laravel's own request-input
        // middleware before validation ever sees it, so the "checked" state
        // must be a real boolean column, never inferred from the reason
        // field's nullability.
        $response = $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'monitoring_plan_not_applicable' => true, 'monitoring_plan_not_applicable_reason' => '',
        ]);

        $response->assertOk();
        $response->assertJsonPath('section.monitoring_plan_not_applicable', true);
        $this->assertTrue($case->fresh()->currentSoap->monitoring_plan_not_applicable);
    }

    public function test_marking_monitoring_plan_not_applicable_clears_any_existing_plan_text(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'monitoring_plan' => 'Weekly renal function.',
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'monitoring_plan_not_applicable' => true,
        ]);

        $response->assertOk();
        $this->assertNull($case->fresh()->currentSoap->monitoring_plan);
        $this->assertTrue($case->fresh()->currentSoap->monitoring_plan_not_applicable);
    }

    public function test_a_single_field_edit_does_not_erase_other_saved_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'subjective' => 'Headache.', 'objective' => 'BP 140/90.',
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'assessment' => 'Tension-type headache.',
        ]);

        $response->assertOk();
        $fresh = $case->fresh()->currentSoap;
        $this->assertSame('Headache.', $fresh->subjective);
        $this->assertSame('BP 140/90.', $fresh->objective);
        $this->assertSame('Tension-type headache.', $fresh->assessment);
    }

    public function test_lock_version_is_not_mass_assignable(): void
    {
        [, $student, $case] = $this->makeCase();
        $soap = SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'revision_number' => 1, 'author_id' => $student->id, 'last_saved_by' => $student->id,
            'lock_version' => 99,
        ]);

        $this->assertSame(0, $soap->fresh()->lock_version);
    }

    public function test_a_stale_base_lock_version_returns_409(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'subjective' => 'First',
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'subjective' => 'Conflicting',
        ])->assertStatus(409);
    }

    public function test_an_unknown_field_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'subjective' => 'x', 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_a_different_student_cannot_sync_the_soap_note(): void
    {
        [$institution, , $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'subjective' => 'x',
        ])->assertForbidden();
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
