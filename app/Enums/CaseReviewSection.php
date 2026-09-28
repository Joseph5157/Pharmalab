<?php

namespace App\Enums;

enum CaseReviewSection: string
{
    case CaseProfile = 'case_profile';
    case HistoryDiagnosis = 'history_diagnosis';
    case VitalsInvestigations = 'vitals_investigations';
    case MedicationChart = 'medication_chart';
    case Soap = 'soap';
    case ClinicalActivities = 'clinical_activities';

    public function label(): string
    {
        return match ($this) {
            self::CaseProfile => 'Case Profile',
            self::HistoryDiagnosis => 'History & Diagnosis',
            self::VitalsInvestigations => 'Vitals & Investigations',
            self::MedicationChart => 'Medication Chart',
            self::Soap => 'SOAP',
            self::ClinicalActivities => 'Conditional Clinical Activities',
        };
    }
}
