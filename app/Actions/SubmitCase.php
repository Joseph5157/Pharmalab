<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Exceptions\CaseNotReadyForSubmissionException;
use App\Models\CaseClinicalActivity;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseStatusTransition;
use App\Models\CaseVersion;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\CaseCompletenessService;
use Illuminate\Support\Facades\DB;

class SubmitCase
{
    public function __construct(
        private readonly AuditTrail $audit,
        private readonly CaseCompletenessService $completeness,
    ) {}

    /**
     * Reachable while the case is Draft, Returned, or Submitted — see
     * ClinicalCasePolicy::submit(). A Draft/Returned case is validated and
     * submitted normally. A Submitted case (a duplicated or retried request)
     * returns the existing version unchanged, without re-validating or
     * re-recording anything — this is what makes a retried POST safe.
     * UnderReview/Approved never reach this method: the policy denies them
     * before the controller runs.
     */
    public function __invoke(User $actor, ClinicalCase $case, bool $deidentificationAttested): CaseVersion
    {
        return DB::transaction(function () use ($actor, $case, $deidentificationAttested): CaseVersion {
            ClinicalCase::query()->whereKey($case->id)->lockForUpdate()->first();
            $case->refresh();

            if ($case->status === CaseStatus::Submitted) {
                $latest = $case->versions()->latest('version_number')->first();
                abort_unless($latest !== null, 500, 'Case is Submitted but has no version — this should be unreachable.');

                return $latest;
            }

            // The authorization check ran before this lock was acquired. If a
            // faculty member moved the case to UnderReview/Approved in that
            // window, a retried/duplicated request must not silently
            // re-validate and move it back to Submitted, overwriting that
            // decision — submission is a one-way door once review has begun.
            abort_unless(in_array($case->status, [CaseStatus::Draft, CaseStatus::Returned], true), 409);

            $errors = $this->completeness->submissionErrors($case);
            if (! $deidentificationAttested) {
                $errors[] = ['section' => 'submission_review', 'message' => 'Confirm the de-identification attestation before submitting.'];
            }
            if ($errors !== []) {
                throw new CaseNotReadyForSubmissionException($errors);
            }

            $case->forceFill([
                'deidentification_attested_at' => now(),
                'deidentification_attested_by' => $actor->id,
            ])->save();

            $snapshot = $this->buildSnapshot($case);
            $versionNumber = $case->current_revision_number + 1;

            $version = CaseVersion::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'version_number' => $versionNumber,
                'source_revision_number' => $case->currentSoap->revision_number,
                'snapshot' => $snapshot,
                'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ]);

            $fromStatus = $case->status;
            $case->update([
                'status' => CaseStatus::Submitted,
                'current_revision_number' => $versionNumber,
                'submitted_at' => now(),
            ]);

            CaseStatusTransition::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'from_status' => $fromStatus->value,
                'to_status' => CaseStatus::Submitted->value,
                'actor_id' => $actor->id,
                'case_version_id' => $version->id,
            ]);

            $this->audit->record($actor, $case, 'clinical_case.submitted', [
                'case_id' => $case->id,
                'version_number' => $versionNumber,
            ]);

            return $version;
        });
    }

    /** @return array<string, mixed> */
    private function buildSnapshot(ClinicalCase $case): array
    {
        $profile = $case->clinicalProfile;
        $soap = $case->currentSoap;

        return [
            'form_version' => $case->form_version->value,
            'case_context' => [
                'encounter_date' => $case->encounter_date?->toDateString(),
                'case_category' => $case->case_category,
                'care_setting' => $case->care_setting,
                'hospital_day_at_first_review' => $case->hospital_day_at_first_review,
                'information_source' => $case->information_source,
                'age_value' => $case->age_value,
                'age_unit' => $case->age_unit,
                'sex' => $case->sex,
                'weight_kg' => $case->weight_kg,
                'height_cm' => $case->height_cm,
                'pregnancy_lactation_status' => $case->pregnancy_lactation_status,
            ],
            'history_diagnosis' => $profile === null ? null : [
                'chief_complaints' => $profile->chief_complaints,
                'history_present_illness' => $profile->history_present_illness,
                'diagnoses' => $profile->diagnoses,
                'past_medical_history' => $profile->past_medical_history,
                'past_medical_history_none' => $profile->past_medical_history_none,
                'past_surgical_history' => $profile->past_surgical_history,
                'adherence_status' => $profile->adherence_status,
                'family_history' => $profile->family_history,
                'substance_history' => $profile->substance_history,
                'examination_findings' => $profile->examination_findings,
                'allergy_status' => $profile->allergy_status,
                'allergy_substance' => $profile->allergy_substance,
                'allergy_reaction' => $profile->allergy_reaction,
            ],
            'vitals' => [
                'status' => $case->vitals_status,
                'unavailable_reason' => $case->vitals_unavailable_reason,
                'entries' => $case->vitals()->orderBy('created_at')->orderBy('id')->get()->map(fn (CaseVital $v): array => [
                    'observation_type' => $v->observation_type,
                    'value_numeric' => $v->value_numeric,
                    'value_text' => $v->value_text,
                    'value_systolic' => $v->value_systolic,
                    'value_diastolic' => $v->value_diastolic,
                    'unit' => $v->unit,
                    'observed_on' => $v->observed_on?->toDateString(),
                    'observed_at_time' => $v->observed_at_time,
                    'source' => $v->source,
                    'note' => $v->note,
                ])->all(),
            ],
            'investigations' => [
                'status' => $case->investigations_status,
                'unavailable_reason' => $case->investigations_unavailable_reason,
                'entries' => $case->investigations()->orderBy('created_at')->orderBy('id')->get()->map(fn (CaseInvestigation $i): array => [
                    'test_name' => $i->test_name,
                    'result_type' => $i->result_type,
                    'result_value' => $i->result_value,
                    'unit' => $i->unit,
                    'unit_not_stated' => $i->unit_not_stated,
                    'reference_range' => $i->reference_range,
                    'reference_range_not_provided' => $i->reference_range_not_provided,
                    'reported_flag' => $i->reported_flag,
                    'observed_on' => $i->observed_on?->toDateString(),
                    'observed_at_time' => $i->observed_at_time,
                    'interpretation' => $i->interpretation,
                ])->all(),
            ],
            // medication_context accepts 'chart'/'history'/null. The shipped
            // Medication Chart section sends null by default but lets a student
            // tag a row 'history'. Record both collections separately, using
            // the same rule the completeness service uses: a row is history
            // only when explicitly tagged 'history'; every other value
            // (including null and 'chart') is part of the chart.
            'medication_history' => [
                'entries' => $case->medications()
                    ->where('medication_context', 'history')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (CaseMedication $m): array => $this->medicationEntry($m))
                    ->all(),
            ],
            'medication_chart' => [
                'status' => $case->medication_chart_status,
                'none_reason' => $case->medication_chart_none_reason,
                'entries' => $case->medications()
                    ->where(fn ($q) => $q->where('medication_context', '!=', 'history')->orWhereNull('medication_context'))
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (CaseMedication $m): array => $this->medicationEntry($m))
                    ->all(),
            ],
            'soap' => $soap === null ? null : [
                'subjective' => $soap->subjective,
                'objective' => $soap->objective,
                'assessment' => $soap->assessment,
                'plan' => $soap->plan,
                'monitoring_plan' => $soap->monitoring_plan,
                'monitoring_plan_not_applicable' => $soap->monitoring_plan_not_applicable,
                'monitoring_plan_not_applicable_reason' => $soap->monitoring_plan_not_applicable_reason,
                'drug_related_problem_status' => $soap->drug_related_problem_status,
                'drug_related_problem_categories' => $soap->drug_related_problem_categories,
            ],
            'clinical_activities' => $case->clinicalActivities()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (CaseClinicalActivity $activity): array => [
                    'activity_type' => $activity->activity_type->value,
                    'status' => $activity->status,
                    'details' => $activity->details,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function medicationEntry(CaseMedication $medication): array
    {
        return [
            'generic_name' => $medication->generic_name,
            'brand_name' => $medication->brand_name,
            'indication' => $medication->indication,
            'indication_unclear' => $medication->indication_unclear,
            'dose_amount' => $medication->dose_amount,
            'dose_unit' => $medication->dose_unit,
            'dosage_form' => $medication->dosage_form,
            'route' => $medication->route,
            'frequency' => $medication->frequency,
            'start_reference' => $medication->start_reference,
            'stop_reference' => $medication->stop_reference,
            'status' => $medication->status?->value,
            'prn_indication' => $medication->prn_indication,
            'notes' => $medication->notes,
        ];
    }
}
