<?php

namespace Tests\Unit\Services;

use App\Enums\CaseStatus;
use App\Models\CaseClinicalActivity;
use App\Models\CaseClinicalProfile;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\SoapNote;
use App\Models\User;
use App\Services\CaseCompletenessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseCompletenessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_brand_new_case_is_missing_every_section(): void
    {
        [, , $case] = $this->makeCase();

        $service = new CaseCompletenessService;

        $this->assertSame([
            'case_profile' => false,
            'history_diagnosis' => false,
            'vitals_investigations' => false,
            'medication_chart' => false,
            'soap' => false,
            'clinical_activities' => false,
        ], $service->sectionCompletion($case->fresh()));
        $this->assertFalse($service->isReadyForSubmission($case->fresh()));
        $this->assertNotSame([], $service->submissionErrors($case->fresh()));
    }

    public function test_a_fully_documented_case_has_every_section_complete_and_no_submission_errors(): void
    {
        [, , $case] = $this->completeCase();

        $service = new CaseCompletenessService;
        $fresh = $case->fresh();

        $this->assertSame([
            'case_profile' => true,
            'history_diagnosis' => true,
            'vitals_investigations' => true,
            'medication_chart' => true,
            'soap' => true,
            'clinical_activities' => true,
        ], $service->sectionCompletion($fresh));
        $this->assertTrue($service->isReadyForSubmission($fresh));
        $this->assertSame([], $service->missingSections($fresh));
        $this->assertSame([], $service->submissionErrors($fresh));
    }

    public function test_missing_diagnosis_and_unanswered_allergy_status_produce_distinct_history_diagnosis_errors(): void
    {
        [$institution, , $case] = $this->completeCase();

        CaseClinicalProfile::query()->withoutGlobalScopes()
            ->where('clinical_case_id', $case->id)
            ->update(['diagnoses' => null, 'allergy_status' => null]);

        $service = new CaseCompletenessService;
        $messages = collect($service->submissionErrors($case->fresh()))->pluck('message')->all();

        $this->assertContains('Enter at least one diagnosis or active problem.', $messages);
        $this->assertContains('Allergy status has not been answered.', $messages);
    }

    public function test_known_allergy_without_a_substance_blocks_submission(): void
    {
        [, , $case] = $this->completeCase();

        CaseClinicalProfile::query()->withoutGlobalScopes()
            ->where('clinical_case_id', $case->id)
            ->update(['allergy_status' => 'known_allergy', 'allergy_substance' => null]);

        $service = new CaseCompletenessService;

        $this->assertFalse($service->sectionCompletion($case->fresh())['history_diagnosis']);
    }

    public function test_vitals_and_investigations_marked_unavailable_with_a_reason_count_as_complete(): void
    {
        [, , $case] = $this->completeCase();

        CaseVital::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->delete();
        CaseInvestigation::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->delete();
        $case->update([
            'vitals_status' => 'unavailable',
            'vitals_unavailable_reason' => 'Ward chart unavailable at time of review.',
            'investigations_status' => 'unavailable',
            'investigations_unavailable_reason' => 'No investigations ordered.',
        ]);

        $service = new CaseCompletenessService;

        $this->assertTrue($service->sectionCompletion($case->fresh())['vitals_investigations']);
    }

    public function test_an_investigation_row_missing_both_unit_and_unit_not_stated_blocks_submission(): void
    {
        [$institution, $student, $case] = $this->completeCase();

        CaseInvestigation::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['unit' => null, 'unit_not_stated' => false]);

        $service = new CaseCompletenessService;
        $fresh = $case->fresh();

        $this->assertFalse($service->sectionCompletion($fresh)['vitals_investigations']);
        $this->assertTrue(collect($service->submissionErrors($fresh))->contains(fn (array $e): bool => str_contains($e['message'], 'investigation')));
    }

    public function test_an_investigation_row_with_unit_not_stated_true_and_no_unit_is_complete(): void
    {
        [, , $case] = $this->completeCase();

        CaseInvestigation::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['unit' => null, 'unit_not_stated' => true]);

        $service = new CaseCompletenessService;

        $this->assertTrue($service->sectionCompletion($case->fresh())['vitals_investigations']);
    }

    public function test_a_medication_row_missing_indication_dose_route_or_frequency_blocks_submission(): void
    {
        [$institution, $student, $case] = $this->completeCase();

        CaseMedication::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['indication' => null, 'indication_unclear' => false, 'route' => null]);

        $service = new CaseCompletenessService;

        $this->assertFalse($service->sectionCompletion($case->fresh())['medication_chart']);
    }

    public function test_medication_context_history_rows_do_not_count_toward_the_chart_and_are_not_independently_required(): void
    {
        [$institution, $student, $case] = $this->completeCase();

        // The shipped UI never creates a 'history' row (see this plan's
        // "Discovered gaps" section) — this proves the service does not
        // silently start requiring one just because it exists in the schema.
        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'medication_context' => 'history',
            'generic_name' => 'Old medicine, incomplete row',
            'status' => 'active',
            'recorded_by' => $student->id,
        ]);

        $service = new CaseCompletenessService;

        $this->assertTrue($service->sectionCompletion($case->fresh())['medication_chart']);
    }

    public function test_medication_chart_status_recorded_but_no_chart_rows_still_blocks_submission(): void
    {
        [, , $case] = $this->completeCase();

        CaseMedication::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->delete();

        $service = new CaseCompletenessService;

        $this->assertFalse($service->sectionCompletion($case->fresh())['medication_chart']);
    }

    public function test_identified_drug_related_problem_without_categories_blocks_submission(): void
    {
        [, , $case] = $this->completeCase();

        SoapNote::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['drug_related_problem_status' => 'identified', 'drug_related_problem_categories' => null]);

        $service = new CaseCompletenessService;

        $this->assertFalse($service->sectionCompletion($case->fresh())['soap']);
    }

    public function test_monitoring_plan_not_applicable_without_a_written_reason_blocks_submission(): void
    {
        [, , $case] = $this->completeCase();

        SoapNote::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['monitoring_plan' => null, 'monitoring_plan_not_applicable' => true, 'monitoring_plan_not_applicable_reason' => null]);

        $service = new CaseCompletenessService;

        $this->assertFalse($service->sectionCompletion($case->fresh())['soap']);
    }

    public function test_identified_drug_related_problem_without_a_matching_intervention_row_blocks_submission(): void
    {
        [$institution, $student, $case] = $this->completeCase();

        SoapNote::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['drug_related_problem_status' => 'identified', 'drug_related_problem_categories' => ['dose_too_low']]);

        $service = new CaseCompletenessService;
        $fresh = $case->fresh();

        $this->assertFalse($service->sectionCompletion($fresh)['clinical_activities']);
        $this->assertContains(
            'A drug-related problem was identified — record a pharmacist intervention with problem and recommendation.',
            collect($service->submissionErrors($fresh))->pluck('message')->all(),
        );

        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'activity_type' => 'intervention',
            'details' => ['problem' => 'Dose too low for renal function.', 'recommendation' => 'Increase to 500mg BD.'],
            'recorded_by' => $student->id,
        ]);

        $this->assertTrue($service->sectionCompletion($case->fresh())['clinical_activities']);
    }

    public function test_suspected_adr_must_be_answered_and_yes_requires_event_and_medicine(): void
    {
        [, , $case] = $this->completeCase();

        $adr = CaseClinicalActivity::query()->withoutGlobalScopes()
            ->where('clinical_case_id', $case->id)->where('activity_type', 'adr')->first();

        $adr->update(['status' => null]);
        $this->assertFalse((new CaseCompletenessService)->sectionCompletion($case->fresh())['clinical_activities']);

        $adr->update(['status' => 'yes', 'details' => null]);
        $this->assertFalse((new CaseCompletenessService)->sectionCompletion($case->fresh())['clinical_activities']);

        $adr->update(['status' => 'no', 'details' => null]);
        $this->assertTrue((new CaseCompletenessService)->sectionCompletion($case->fresh())['clinical_activities']);
    }

    public function test_missing_adr_row_entirely_blocks_submission(): void
    {
        [, , $case] = $this->completeCase();

        CaseClinicalActivity::query()->withoutGlobalScopes()
            ->where('clinical_case_id', $case->id)->where('activity_type', 'adr')->delete();

        $service = new CaseCompletenessService;
        $fresh = $case->fresh();

        $this->assertFalse($service->sectionCompletion($fresh)['clinical_activities']);
        $this->assertContains('Suspected ADR has not been answered.', collect($service->submissionErrors($fresh))->pluck('message')->all());
    }

    public function test_counselling_status_unanswered_blocks_submission(): void
    {
        [, , $case] = $this->completeCase();

        CaseClinicalActivity::query()->withoutGlobalScopes()
            ->where('clinical_case_id', $case->id)->where('activity_type', 'counselling')
            ->update(['status' => null]);

        $service = new CaseCompletenessService;
        $this->assertFalse($service->sectionCompletion($case->fresh())['clinical_activities']);
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(): array
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
        ]);

        return [$institution, $student, $case];
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function completeCase(): array
    {
        [$institution, $student, $case] = $this->makeCase();

        $case->update([
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
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'pulse',
            'value_numeric' => 80,
            'recorded_by' => $student->id,
        ]);

        CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'test_name' => 'Haemoglobin',
            'result_type' => 'numeric',
            'result_value' => '13.5',
            'unit' => 'g/dL',
            'reference_range_not_provided' => true,
            'recorded_by' => $student->id,
        ]);

        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'medication_context' => null,
            'generic_name' => 'Paracetamol',
            'indication' => 'Headache relief',
            'dose_amount' => '500',
            'dose_unit' => 'mg',
            'route' => 'oral',
            'frequency' => 'TID',
            'status' => 'active',
            'recorded_by' => $student->id,
        ]);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'S',
            'objective' => 'O',
            'assessment' => 'A',
            'plan' => 'P',
            'monitoring_plan' => 'Review pain score at 24h.',
            'drug_related_problem_status' => 'none_identified',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'activity_type' => 'adr',
            'status' => 'no',
            'recorded_by' => $student->id,
        ]);

        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'activity_type' => 'counselling',
            'status' => 'not_indicated',
            'recorded_by' => $student->id,
        ]);

        return [$institution, $student, $case];
    }
}
