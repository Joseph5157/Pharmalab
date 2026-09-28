<?php

namespace App\Services;

use App\Enums\CaseStatus;
use App\Enums\ClinicalActivityType;
use App\Models\CaseClinicalActivity;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseReviewComment;
use App\Models\CaseVital;
use App\Models\ClinicalCase;

class ClinicalCasePresenter
{
    /** @return array<string, mixed> */
    public function context(ClinicalCase $case): array
    {
        $case->loadMissing(['rotationAssignment.rotation', 'clinicalSite', 'ward']);

        return [
            'encounter_date' => $case->encounter_date?->toDateString(),
            'case_category' => $case->case_category,
            'age_value' => $case->age_value,
            'age_unit' => $case->age_unit,
            'sex' => $case->sex,
            'care_setting' => $case->care_setting,
            'hospital_day_at_first_review' => $case->hospital_day_at_first_review,
            'information_source' => $case->information_source,
            'weight_kg' => $case->weight_kg,
            'height_cm' => $case->height_cm,
            'pregnancy_lactation_status' => $case->pregnancy_lactation_status,
            'case_display' => [
                'case_number' => $case->case_number,
                'rotation_name' => $case->rotationAssignment?->rotation?->name,
                'clinical_site_name' => $case->clinicalSite?->name,
                'ward_name' => $case->ward?->name,
            ],
            'vitals_status' => $case->vitals_status,
            'vitals_unavailable_reason' => $case->vitals_unavailable_reason,
            'vitals_availability_lock_version' => $case->vitals_availability_lock_version,
            'investigations_status' => $case->investigations_status,
            'investigations_unavailable_reason' => $case->investigations_unavailable_reason,
            'investigations_availability_lock_version' => $case->investigations_availability_lock_version,
            'medication_chart_status' => $case->medication_chart_status,
            'medication_chart_none_reason' => $case->medication_chart_none_reason,
            'medication_chart_availability_lock_version' => $case->medication_chart_availability_lock_version,
            'lock_version' => $case->lock_version,
            'updated_at' => $case->updated_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function clinicalProfile(ClinicalCase $case): ?array
    {
        $profile = $case->clinicalProfile;

        return $profile === null ? null : [
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
            'lock_version' => $profile->lock_version,
            'updated_at' => $profile->updated_at->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function vitals(ClinicalCase $case): array
    {
        return array_values($case->vitals()->orderByDesc('created_at')->get()->map(fn (CaseVital $vital): array => [
            'id' => $vital->id,
            'observation_type' => $vital->observation_type,
            'value_numeric' => $vital->value_numeric,
            'value_text' => $vital->value_text,
            'value_systolic' => $vital->value_systolic,
            'value_diastolic' => $vital->value_diastolic,
            'unit' => $vital->unit,
            'observed_on' => $vital->observed_on?->toDateString(),
            'observed_at_time' => $vital->observed_at_time,
            'source' => $vital->source,
            'note' => $vital->note,
            'lock_version' => $vital->lock_version,
            'updated_at' => $vital->updated_at->toIso8601String(),
        ])->all());
    }

    /** @return list<array<string, mixed>> */
    public function investigations(ClinicalCase $case): array
    {
        return array_values($case->investigations()->orderByDesc('created_at')->get()->map(fn (CaseInvestigation $investigation): array => [
            'id' => $investigation->id,
            'test_name' => $investigation->test_name,
            'result_type' => $investigation->result_type,
            'result_value' => $investigation->result_value,
            'unit' => $investigation->unit,
            'unit_not_stated' => $investigation->unit_not_stated,
            'reference_range' => $investigation->reference_range,
            'reference_range_not_provided' => $investigation->reference_range_not_provided,
            'reported_flag' => $investigation->reported_flag,
            'observed_on' => $investigation->observed_on?->toDateString(),
            'observed_at_time' => $investigation->observed_at_time,
            'interpretation' => $investigation->interpretation,
            'lock_version' => $investigation->lock_version,
            'updated_at' => $investigation->updated_at->toIso8601String(),
        ])->all());
    }

    /** @return list<array<string, mixed>> */
    public function medications(ClinicalCase $case): array
    {
        return array_values($case->medications()->orderByDesc('created_at')->get()->map(fn (CaseMedication $medication): array => [
            'id' => $medication->id,
            'medication_context' => $medication->medication_context,
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
            'lock_version' => $medication->lock_version,
            'updated_at' => $medication->updated_at->toIso8601String(),
        ])->all());
    }

    /** @return array<string, mixed>|null */
    public function soap(ClinicalCase $case): ?array
    {
        $soap = $case->currentSoap;

        return $soap === null ? null : [
            'subjective' => $soap->subjective,
            'objective' => $soap->objective,
            'assessment' => $soap->assessment,
            'plan' => $soap->plan,
            'monitoring_plan' => $soap->monitoring_plan,
            'monitoring_plan_not_applicable' => $soap->monitoring_plan_not_applicable,
            'monitoring_plan_not_applicable_reason' => $soap->monitoring_plan_not_applicable_reason,
            'drug_related_problem_status' => $soap->drug_related_problem_status,
            'drug_related_problem_categories' => $soap->drug_related_problem_categories,
            'lock_version' => $soap->lock_version,
            'updated_at' => $soap->updated_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function singletonActivity(ClinicalCase $case, ClinicalActivityType $type): ?array
    {
        $activity = $case->clinicalActivities->firstWhere('activity_type', $type);

        return $activity === null ? null : $this->activityPayload($activity);
    }

    /** @return list<array<string, mixed>> */
    public function repeatableActivity(ClinicalCase $case, ClinicalActivityType $type): array
    {
        return array_values($case->clinicalActivities
            ->where('activity_type', $type)
            ->map(fn (CaseClinicalActivity $activity): array => $this->activityPayload($activity))
            ->all());
    }

    /** @return array{flaggedSections: list<string>, reopenedReason: string|null} */
    public function reviewFeedback(ClinicalCase $case): array
    {
        if ($case->status !== CaseStatus::Returned) {
            return ['flaggedSections' => [], 'reopenedReason' => null];
        }

        $transition = $case->statusTransitions()
            ->where('to_status', CaseStatus::Returned->value)
            ->latest('created_at')
            ->with('reviewComments')
            ->first();

        if ($transition === null) {
            return ['flaggedSections' => [], 'reopenedReason' => null];
        }

        if ($transition->from_status === CaseStatus::Approved->value) {
            return ['flaggedSections' => [], 'reopenedReason' => $transition->reason];
        }

        $flaggedSections = array_values($transition->reviewComments
            ->where('is_flagged', true)
            ->map(fn (CaseReviewComment $comment): string => $comment->section->value)
            ->all());

        return ['flaggedSections' => $flaggedSections, 'reopenedReason' => null];
    }

    /** @return array<string, mixed> */
    private function activityPayload(CaseClinicalActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'activity_type' => $activity->activity_type->value,
            'status' => $activity->status,
            'details' => $activity->details,
            'lock_version' => $activity->lock_version,
            'updated_at' => $activity->updated_at->toIso8601String(),
        ];
    }
}
