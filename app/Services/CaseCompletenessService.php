<?php

namespace App\Services;

use App\Models\ClinicalCase;

class CaseCompletenessService
{
    /** @return array<string, bool> */
    public function sectionCompletion(ClinicalCase $case): array
    {
        $profile = $case->clinicalProfile;
        $soap = $case->currentSoap;

        return [
            'case_context' => filled($case->care_setting) && $case->age_value !== null && filled($case->sex),
            'history_and_diagnosis' => $profile !== null
                && filled($profile->history_present_illness)
                && filled($profile->diagnoses)
                && ($profile->past_medical_history_none || filled($profile->past_medical_history))
                && filled($profile->allergy_status),
            'vitals' => $case->vitals()->exists(),
            'investigations' => $case->investigations()->exists(),
            'medication_chart' => $case->medications()->where('medication_context', 'chart')->exists(),
            'soap' => $soap !== null
                && filled($soap->subjective)
                && filled($soap->objective)
                && filled($soap->assessment)
                && filled($soap->plan),
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
        return $this->missingSections($case) === [];
    }
}
