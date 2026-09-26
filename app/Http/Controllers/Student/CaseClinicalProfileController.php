<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateCaseClinicalProfileRequest;
use App\Models\CaseClinicalProfile;
use App\Models\ClinicalCase;
use App\Services\SectionSyncService;
use Illuminate\Http\JsonResponse;

class CaseClinicalProfileController extends Controller
{
    public function sync(UpdateCaseClinicalProfileRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $profile = CaseClinicalProfile::query()->firstOrCreate(
            ['clinical_case_id' => $case->id],
            ['institution_id' => $case->institution_id, 'last_saved_by' => $request->user()->id],
        );
        $envelope = $request->syncEnvelope();
        $data = $request->sectionData();

        if (array_key_exists('allergy_status', $data) && $data['allergy_status'] !== 'known_allergy') {
            $data['allergy_substance'] = null;
            $data['allergy_reaction'] = null;
        }

        if (array_key_exists('past_medical_history_none', $data) && $data['past_medical_history_none']) {
            $data['past_medical_history'] = null;
        } elseif (array_key_exists('past_medical_history', $data) && $data['past_medical_history'] !== null && $data['past_medical_history'] !== '') {
            $data['past_medical_history_none'] = false;
        }

        $result = $sync->sync(
            $profile,
            $request->user(),
            'clinical_profile',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            [...$data, 'last_saved_by' => $request->user()->id],
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        $model = $result['model'];
        abort_unless($model instanceof CaseClinicalProfile, 500, 'Unexpected model type returned from sync.');

        return response()->json(['section' => $this->payload($model)], $result['httpStatus']);
    }

    /** @return array<string, mixed> */
    private function payload(CaseClinicalProfile $profile): array
    {
        return [
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
}
