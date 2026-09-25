<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateClinicalCaseContextRequest;
use App\Models\ClinicalCase;
use App\Services\SectionSyncService;
use Illuminate\Http\JsonResponse;

class CaseContextController extends Controller
{
    public function sync(UpdateClinicalCaseContextRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $e = $request->syncEnvelope(); $result = $sync->sync($case, $request->user(), 'case_context', $e['client_operation_id'], $e['base_lock_version'], $request->sectionData(), $e['resolution'], $e['confirmed']);
        return response()->json(['section' => $this->payload($result['model'])], $result['httpStatus']);
    }
    /** @return array<string, mixed> */
    private function payload(ClinicalCase $case): array
    {
        $case->loadMissing(['rotationAssignment.rotation', 'clinicalSite', 'ward']);
        return ['encounter_date' => $case->encounter_date?->toDateString(), 'case_category' => $case->case_category, 'age_value' => $case->age_value, 'age_unit' => $case->age_unit, 'sex' => $case->sex, 'care_setting' => $case->care_setting, 'hospital_day_at_first_review' => $case->hospital_day_at_first_review, 'information_source' => $case->information_source, 'weight_kg' => $case->weight_kg, 'height_cm' => $case->height_cm, 'pregnancy_lactation_status' => $case->pregnancy_lactation_status, 'case_display' => ['case_number' => $case->case_number, 'rotation_name' => $case->rotationAssignment?->rotation?->name, 'clinical_site_name' => $case->clinicalSite?->name, 'ward_name' => $case->ward?->name], 'lock_version' => $case->lock_version, 'updated_at' => $case->updated_at->toIso8601String()];
    }
}
