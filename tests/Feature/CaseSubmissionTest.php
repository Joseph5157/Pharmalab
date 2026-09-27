<?php

namespace Tests\Feature;

use App\Actions\SubmitCase;
use App\Enums\CaseStatus;
use App\Exceptions\CaseNotReadyForSubmissionException;
use App\Models\CaseClinicalActivity;
use App\Models\CaseClinicalProfile;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVersion;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\SoapNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CaseSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_incomplete_case_cannot_be_submitted(): void
    {
        [, $student, , $case] = $this->completeCase();
        CaseVital::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->delete();
        $case->update(['vitals_status' => null]);

        $this->expectException(CaseNotReadyForSubmissionException::class);

        app(SubmitCase::class)->__invoke($student, $case->fresh(), true);
    }

    public function test_submission_without_deidentification_attestation_is_blocked(): void
    {
        [, $student, , $case] = $this->completeCase();

        try {
            app(SubmitCase::class)->__invoke($student, $case->fresh(), false);
            $this->fail('Expected CaseNotReadyForSubmissionException.');
        } catch (CaseNotReadyForSubmissionException $e) {
            $this->assertTrue(collect($e->errors())->contains(fn (array $error): bool => str_contains($error['message'], 'de-identification attestation')));
        }

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Draft->value]);
    }

    public function test_a_complete_attested_case_submits_and_records_a_full_form_versioned_snapshot(): void
    {
        [, $student, , $case] = $this->completeCase();

        $version = app(SubmitCase::class)->__invoke($student, $case->fresh(), true);

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
        $fresh = $case->fresh();
        $this->assertNotNull($fresh->deidentification_attested_at);
        $this->assertSame($student->id, $fresh->deidentification_attested_by);

        $this->assertSame('pharmd-case-v1', $version->snapshot['form_version']);
        $this->assertSame('S', $version->snapshot['soap']['subjective']);
        $this->assertSame('Paracetamol', $version->snapshot['medication_chart']['entries'][0]['generic_name']);
        $this->assertSame('recorded', $version->snapshot['vitals']['status']);
    }

    public function test_snapshot_records_medication_history_and_chart_separately_by_medication_context(): void
    {
        [, $student, , $case] = $this->completeCase();

        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id,
            'clinical_case_id' => $case->id,
            'medication_context' => 'history',
            'generic_name' => 'Warfarin',
            'indication' => 'Prior anticoagulation',
            'dose_amount' => '5',
            'dose_unit' => 'mg',
            'route' => 'oral',
            'frequency' => 'OD',
            'status' => 'stopped',
            'recorded_by' => $student->id,
        ]);

        $version = app(SubmitCase::class)->__invoke($student, $case->fresh(), true);

        $this->assertSame(
            ['Paracetamol'],
            array_column($version->snapshot['medication_chart']['entries'], 'generic_name'),
        );
        $this->assertSame(
            ['Warfarin'],
            array_column($version->snapshot['medication_history']['entries'], 'generic_name'),
        );
    }

    public function test_snapshot_repeatable_collections_are_ordered_by_created_at_then_id(): void
    {
        [$institution, $student, , $case] = $this->completeCase();

        // The fixture rows are dated 09:00. Add one earlier and one later per
        // collection so the deterministic expected order differs from the
        // order the rows were inserted (and, for clinical activities, from
        // relation-load order).
        CaseVital::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['created_at' => '2026-10-15 09:00:00']);
        $lateVital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'observation_type' => 'temperature', 'value_numeric' => 38, 'recorded_by' => $student->id,
        ]);
        $lateVital->forceFill(['created_at' => '2026-10-15 10:00:00'])->saveQuietly();
        $earlyVital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'observation_type' => 'respiratory_rate', 'value_numeric' => 18, 'recorded_by' => $student->id,
        ]);
        $earlyVital->forceFill(['created_at' => '2026-10-15 08:00:00'])->saveQuietly();

        CaseInvestigation::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['created_at' => '2026-10-15 09:00:00']);
        $lateInvestigation = CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'test_name' => 'Sodium', 'result_type' => 'numeric', 'result_value' => '140',
            'unit' => 'mmol/L', 'reference_range_not_provided' => true, 'recorded_by' => $student->id,
        ]);
        $lateInvestigation->forceFill(['created_at' => '2026-10-15 10:00:00'])->saveQuietly();
        $earlyInvestigation = CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'test_name' => 'Potassium', 'result_type' => 'numeric', 'result_value' => '4.0',
            'unit' => 'mmol/L', 'reference_range_not_provided' => true, 'recorded_by' => $student->id,
        ]);
        $earlyInvestigation->forceFill(['created_at' => '2026-10-15 08:00:00'])->saveQuietly();

        CaseMedication::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['created_at' => '2026-10-15 09:00:00']);
        $lateMedication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'medication_context' => null, 'generic_name' => 'Ibuprofen', 'indication' => 'Pain',
            'dose_amount' => '400', 'dose_unit' => 'mg', 'route' => 'oral', 'frequency' => 'TID',
            'status' => 'active', 'recorded_by' => $student->id,
        ]);
        $lateMedication->forceFill(['created_at' => '2026-10-15 10:00:00'])->saveQuietly();
        $earlyMedication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'medication_context' => null, 'generic_name' => 'Aspirin', 'indication' => 'Antiplatelet',
            'dose_amount' => '75', 'dose_unit' => 'mg', 'route' => 'oral', 'frequency' => 'OD',
            'status' => 'active', 'recorded_by' => $student->id,
        ]);
        $earlyMedication->forceFill(['created_at' => '2026-10-15 08:00:00'])->saveQuietly();

        CaseClinicalActivity::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['created_at' => '2026-10-15 09:00:00']);
        $lateActivity = CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'monitoring', 'status' => 'in_progress', 'recorded_by' => $student->id,
        ]);
        $lateActivity->forceFill(['created_at' => '2026-10-15 10:00:00'])->saveQuietly();
        $earlyActivity = CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'monitoring', 'status' => 'planned', 'recorded_by' => $student->id,
        ]);
        $earlyActivity->forceFill(['created_at' => '2026-10-15 08:00:00'])->saveQuietly();

        $version = app(SubmitCase::class)->__invoke($student, $case->fresh(), true);

        $this->assertSame(
            ['respiratory_rate', 'pulse', 'temperature'],
            array_column($version->snapshot['vitals']['entries'], 'observation_type'),
        );
        $this->assertSame(
            ['Potassium', 'Haemoglobin', 'Sodium'],
            array_column($version->snapshot['investigations']['entries'], 'test_name'),
        );
        $this->assertSame(
            ['Aspirin', 'Paracetamol', 'Ibuprofen'],
            array_column($version->snapshot['medication_chart']['entries'], 'generic_name'),
        );

        $monitoring = array_values(array_filter(
            $version->snapshot['clinical_activities'],
            fn (array $activity): bool => $activity['activity_type'] === 'monitoring',
        ));
        $this->assertSame(['planned', 'in_progress'], array_column($monitoring, 'status'));
    }

    public function test_resubmitting_an_already_submitted_case_at_the_action_level_is_idempotent(): void
    {
        [, $student, , $case] = $this->completeCase();

        $first = app(SubmitCase::class)->__invoke($student, $case->fresh(), true);
        $second = app(SubmitCase::class)->__invoke($student, $case->fresh(), true);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->count());
    }

    public function test_submit_refuses_a_case_that_has_moved_to_under_review_or_approved_since_the_authorization_check(): void
    {
        [, $student, , $case] = $this->completeCase();

        // Simulates the race the policy check alone cannot close: by the time
        // SubmitCase acquires its row lock, a faculty member has already
        // moved the case past Submitted. A retried/duplicated request must
        // not silently re-submit and overwrite that decision.
        $case->update(['status' => CaseStatus::UnderReview]);

        try {
            app(SubmitCase::class)->__invoke($student, $case->fresh(), true);
            $this->fail('Expected an HttpException with status 409.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::UnderReview->value]);
    }

    public function test_a_repeated_http_submit_on_an_already_submitted_case_succeeds_idempotently(): void
    {
        [, $student, , $case] = $this->completeCase();
        $this->actingAs($student);

        $first = $this->post(route('student.cases.submit', $case), ['deidentification_attested' => true]);
        $first->assertRedirect(route('student.cases.show', $case));

        $second = $this->post(route('student.cases.submit', $case), ['deidentification_attested' => true]);
        $second->assertRedirect(route('student.cases.show', $case));

        $this->assertSame(1, CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->count());
    }

    public function test_unknown_fields_are_rejected_when_submitting_through_the_http_endpoint(): void
    {
        [, $student, , $case] = $this->completeCase();
        $this->actingAs($student);

        $response = $this->post(route('student.cases.submit', $case), [
            'deidentification_attested' => true,
            'status' => 'approved',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Draft->value]);
    }

    public function test_missing_attestation_is_rejected_when_submitting_through_the_http_endpoint(): void
    {
        [, $student, , $case] = $this->completeCase();
        $this->actingAs($student);

        $response = $this->post(route('student.cases.submit', $case), []);

        $response->assertSessionHasErrors('deidentification_attested');
        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Draft->value]);
    }

    /** @return array{Institution, User, User, ClinicalCase} */
    private function completeCase(): array
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
            'status' => CaseStatus::Draft,
            'form_version' => 'pharmd-case-v1',
            'care_setting' => 'inpatient',
            'encounter_date' => '2026-10-15',
            'information_source' => 'case sheet',
            'age_value' => 34,
            'age_unit' => 'years',
            'sex' => 'female',
            'vitals_status' => 'recorded',
            'investigations_status' => 'recorded',
            'medication_chart_status' => 'documented',
        ]);

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'chief_complaints' => [['complaint' => 'Headache', 'duration' => '3 days']],
            'history_present_illness' => 'Three-day headache, no red flags.',
            'diagnoses' => [['label' => 'Tension headache', 'type' => 'provisional']],
            'past_medical_history_none' => true,
            'allergy_status' => 'no_known_allergy',
            'last_saved_by' => $student->id,
        ]);

        CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
        ]);

        CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'test_name' => 'Haemoglobin', 'result_type' => 'numeric', 'result_value' => '13.5',
            'unit' => 'g/dL', 'reference_range_not_provided' => true, 'recorded_by' => $student->id,
        ]);

        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'medication_context' => null, 'generic_name' => 'Paracetamol', 'indication' => 'Headache relief',
            'dose_amount' => '500', 'dose_unit' => 'mg', 'route' => 'oral', 'frequency' => 'TID',
            'status' => 'active', 'recorded_by' => $student->id,
        ]);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'S', 'objective' => 'O', 'assessment' => 'A', 'plan' => 'P',
            'monitoring_plan' => 'Review pain score at 24h.',
            'drug_related_problem_status' => 'none_identified',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'adr', 'status' => 'no', 'recorded_by' => $student->id,
        ]);
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'counselling', 'status' => 'not_indicated', 'recorded_by' => $student->id,
        ]);

        return [$institution, $student, $faculty, $case];
    }
}
