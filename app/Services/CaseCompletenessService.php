<?php

namespace App\Services;

use App\Enums\ClinicalActivityType;
use App\Models\CaseClinicalActivity;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\ClinicalCase;

class CaseCompletenessService
{
    /** @return array<string, bool> */
    public function sectionCompletion(ClinicalCase $case): array
    {
        return [
            'case_profile' => $this->caseProfileComplete($case),
            'history_diagnosis' => $this->historyDiagnosisComplete($case),
            'vitals_investigations' => $this->vitalsComplete($case) && $this->investigationsComplete($case),
            'medication_chart' => $this->medicationChartComplete($case),
            'soap' => $this->soapComplete($case),
            'clinical_activities' => $this->adrComplete($case) && $this->counsellingComplete($case) && $this->interventionComplete($case),
        ];
    }

    /** @return list<string> */
    public function missingSections(ClinicalCase $case): array
    {
        return array_keys(array_filter(
            $this->sectionCompletion($case),
            fn (bool $complete): bool => ! $complete,
        ));
    }

    public function isReadyForSubmission(ClinicalCase $case): bool
    {
        return $this->submissionErrors($case) === [];
    }

    /** @return list<array{section: string, message: string}> */
    public function submissionErrors(ClinicalCase $case): array
    {
        $errors = [];

        if (! $this->caseProfileComplete($case)) {
            $errors[] = ['section' => 'case_profile', 'message' => 'Complete the case profile: care setting, documentation date, information source, age and sex are all required.'];
        }

        $profile = $case->clinicalProfile;

        if ($profile === null || ! filled($profile->diagnoses)) {
            $errors[] = ['section' => 'history_diagnosis', 'message' => 'Enter at least one diagnosis or active problem.'];
        }
        if ($profile === null || ! filled($profile->chief_complaints)) {
            $errors[] = ['section' => 'history_diagnosis', 'message' => 'Enter at least one chief complaint.'];
        }
        if ($profile === null || ! filled($profile->history_present_illness)) {
            $errors[] = ['section' => 'history_diagnosis', 'message' => 'History of present illness is required.'];
        }
        if ($profile === null || (! $profile->past_medical_history_none && ! filled($profile->past_medical_history))) {
            $errors[] = ['section' => 'history_diagnosis', 'message' => 'Enter past medical history, or select "None known".'];
        }
        if ($profile === null || ! filled($profile->allergy_status)) {
            $errors[] = ['section' => 'history_diagnosis', 'message' => 'Allergy status has not been answered.'];
        } elseif ($profile->allergy_status === 'known_allergy' && ! filled($profile->allergy_substance)) {
            $errors[] = ['section' => 'history_diagnosis', 'message' => 'Enter the allergy substance for the recorded known allergy.'];
        }

        if (! $this->vitalsComplete($case)) {
            $errors[] = ['section' => 'vitals_investigations', 'message' => 'Record at least one vital sign, or mark vitals unavailable with a reason.'];
        }
        if (! $this->investigationsComplete($case)) {
            $errors[] = ['section' => 'vitals_investigations', 'message' => 'Record at least one complete investigation result (test name, result, and a unit or "Unit not stated"), or mark investigations unavailable with a reason.'];
        }

        if (! $this->medicationChartComplete($case)) {
            $errors[] = ['section' => 'medication_chart', 'message' => 'Add at least one complete current medicine (generic name, indication or "Indication unclear", dose amount and unit, route and frequency), or select "No current medicines documented".'];
        }

        $soap = $case->currentSoap;
        if ($soap === null || ! filled($soap->subjective) || ! filled($soap->objective) || ! filled($soap->assessment) || ! filled($soap->plan)) {
            $errors[] = ['section' => 'soap', 'message' => 'All four SOAP areas (Subjective, Objective, Assessment, Plan) must be completed.'];
        }
        if ($soap !== null) {
            if (! filled($soap->drug_related_problem_status)) {
                $errors[] = ['section' => 'soap', 'message' => 'Select a drug-related-problem status in the Assessment.'];
            } elseif ($soap->drug_related_problem_status === 'identified' && ! filled($soap->drug_related_problem_categories)) {
                $errors[] = ['section' => 'soap', 'message' => 'Select at least one drug-related-problem category.'];
            }
            if ($soap->monitoring_plan_not_applicable) {
                if (! filled($soap->monitoring_plan_not_applicable_reason)) {
                    $errors[] = ['section' => 'soap', 'message' => 'Give a written justification for why a monitoring plan is not applicable.'];
                }
            } elseif (! filled($soap->monitoring_plan)) {
                $errors[] = ['section' => 'soap', 'message' => 'Enter a monitoring plan, or mark it not applicable with a written justification.'];
            }
        }

        $adr = $case->clinicalActivities->firstWhere('activity_type', ClinicalActivityType::Adr);
        if ($adr === null || ! filled($adr->status)) {
            $errors[] = ['section' => 'clinical_activities', 'message' => 'Suspected ADR has not been answered.'];
        } elseif ($adr->status === 'yes') {
            $details = $adr->details ?? [];
            if (blank($details['event'] ?? null) || blank($details['suspected_medicine'] ?? null)) {
                $errors[] = ['section' => 'clinical_activities', 'message' => 'Suspected ADR is "Yes" but the ADR event and suspected medicine are not recorded.'];
            }
        }

        $counselling = $case->clinicalActivities->firstWhere('activity_type', ClinicalActivityType::Counselling);
        if ($counselling === null || ! filled($counselling->status)) {
            $errors[] = ['section' => 'clinical_activities', 'message' => 'Patient counselling status has not been answered.'];
        }

        if (! $this->interventionComplete($case)) {
            $errors[] = ['section' => 'clinical_activities', 'message' => 'A drug-related problem was identified — record a pharmacist intervention with problem and recommendation.'];
        }

        return $errors;
    }

    private function caseProfileComplete(ClinicalCase $case): bool
    {
        return filled($case->care_setting)
            && $case->encounter_date !== null
            && filled($case->information_source)
            && $case->age_value !== null
            && filled($case->sex);
    }

    private function historyDiagnosisComplete(ClinicalCase $case): bool
    {
        $profile = $case->clinicalProfile;

        return $profile !== null
            && filled($profile->chief_complaints)
            && filled($profile->history_present_illness)
            && filled($profile->diagnoses)
            && ($profile->past_medical_history_none || filled($profile->past_medical_history))
            && filled($profile->allergy_status)
            && ($profile->allergy_status !== 'known_allergy' || filled($profile->allergy_substance));
    }

    private function vitalsComplete(ClinicalCase $case): bool
    {
        return ($case->vitals_status === 'recorded' && $case->vitals()->exists())
            || $case->vitals_status === 'unavailable';
    }

    private function investigationsComplete(ClinicalCase $case): bool
    {
        if ($case->investigations_status === 'unavailable') {
            return true;
        }
        if ($case->investigations_status !== 'recorded') {
            return false;
        }
        $investigations = $case->investigations;

        return $investigations->isNotEmpty()
            && $investigations->every(fn (CaseInvestigation $i): bool => $this->investigationRowComplete($i));
    }

    private function investigationRowComplete(CaseInvestigation $investigation): bool
    {
        return filled($investigation->test_name)
            && filled($investigation->result_type)
            && filled($investigation->result_value)
            && (filled($investigation->unit) || $investigation->unit_not_stated)
            && (filled($investigation->reference_range) || $investigation->reference_range_not_provided);
    }

    private function medicationChartComplete(ClinicalCase $case): bool
    {
        if ($case->medication_chart_status === 'none_documented') {
            return true;
        }
        if ($case->medication_chart_status !== 'documented') {
            return false;
        }

        // medication_context accepts 'chart'/'history'/null at the request
        // layer, but the shipped Medication Chart section always sends null
        // (MedicationChartSection.vue's emptyPayload()) — filtering on the
        // literal string 'chart' (the pre-Slice-3 behavior) matched zero
        // real rows. See this plan's "Discovered gaps and scope
        // reconciliations" section. Every row not explicitly tagged
        // 'history' counts as a chart row.
        $medications = $case->medications->reject(fn (CaseMedication $m): bool => $m->medication_context === 'history');

        return $medications->isNotEmpty()
            && $medications->every(fn (CaseMedication $m): bool => $this->medicationRowComplete($m));
    }

    private function medicationRowComplete(CaseMedication $medication): bool
    {
        return filled($medication->generic_name)
            && (filled($medication->indication) || $medication->indication_unclear)
            && filled($medication->dose_amount)
            && filled($medication->dose_unit)
            && filled($medication->route)
            && filled($medication->frequency)
            && $medication->status !== null;
    }

    private function soapComplete(ClinicalCase $case): bool
    {
        $soap = $case->currentSoap;
        if ($soap === null || ! filled($soap->subjective) || ! filled($soap->objective) || ! filled($soap->assessment) || ! filled($soap->plan)) {
            return false;
        }
        if (! filled($soap->drug_related_problem_status)) {
            return false;
        }
        if ($soap->drug_related_problem_status === 'identified' && ! filled($soap->drug_related_problem_categories)) {
            return false;
        }

        return $soap->monitoring_plan_not_applicable
            ? filled($soap->monitoring_plan_not_applicable_reason)
            : filled($soap->monitoring_plan);
    }

    private function adrComplete(ClinicalCase $case): bool
    {
        $adr = $case->clinicalActivities->firstWhere('activity_type', ClinicalActivityType::Adr);
        if ($adr === null || ! filled($adr->status)) {
            return false;
        }
        if ($adr->status !== 'yes') {
            return true;
        }
        $details = $adr->details ?? [];

        return filled($details['event'] ?? null) && filled($details['suspected_medicine'] ?? null);
    }

    private function counsellingComplete(ClinicalCase $case): bool
    {
        $counselling = $case->clinicalActivities->firstWhere('activity_type', ClinicalActivityType::Counselling);

        return $counselling !== null && filled($counselling->status);
    }

    private function interventionComplete(ClinicalCase $case): bool
    {
        $soap = $case->currentSoap;
        if ($soap === null || $soap->drug_related_problem_status !== 'identified') {
            return true;
        }

        return $case->clinicalActivities
            ->where('activity_type', ClinicalActivityType::Intervention)
            ->contains(fn (CaseClinicalActivity $activity): bool => filled($activity->details['problem'] ?? null) && filled($activity->details['recommendation'] ?? null));
    }
}
