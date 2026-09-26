<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CaseEditorController extends Controller
{
    public function show(ClinicalCase $case): Response
    {
        Gate::authorize('update', $case);

        $case->loadMissing(['rotationAssignment.rotation', 'clinicalSite', 'ward']);
        $profile = $case->clinicalProfile;

        return Inertia::render('student/CaseEditor', [
            'clinicalCase' => $case->only(['id', 'case_number', 'status']),
            'userId' => request()->user()->id,
            'context' => [
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
                'lock_version' => $case->lock_version,
                'updated_at' => $case->updated_at->toIso8601String(),
            ],
            'clinicalProfile' => $profile === null ? null : [
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
            ],
        ]);
    }
}
