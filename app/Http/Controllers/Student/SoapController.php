<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateSoapNoteRequest;
use App\Models\ClinicalCase;
use App\Models\SoapNote;
use App\Services\AuditTrail;
use App\Services\SectionSyncService;
use Illuminate\Http\JsonResponse;

class SoapController extends Controller
{
    public function sync(UpdateSoapNoteRequest $request, ClinicalCase $case, SectionSyncService $sync, AuditTrail $audit): JsonResponse
    {
        $wasNew = $case->currentSoap === null;
        $soap = $case->currentSoap ?? SoapNote::query()->create([
            'institution_id' => $case->institution_id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'author_id' => $request->user()->id,
            'last_saved_by' => $request->user()->id,
        ]);

        $envelope = $request->syncEnvelope();
        $data = $request->sectionData();

        // monitoring_plan_not_applicable is a genuine boolean flag, not
        // inferred from monitoring_plan_not_applicable_reason's nullability —
        // Laravel's ConvertEmptyStringsToNull middleware normalizes an empty
        // reason string to null before validation, so a reason-nullability-only
        // signal can never represent "checked, no reason typed yet" (Task 9
        // live device verification: the box appeared to check but a reload
        // always reverted it). Mirrors CaseClinicalProfileController's
        // past_medical_history_none clearing precedent.
        if (array_key_exists('monitoring_plan_not_applicable', $data) && $data['monitoring_plan_not_applicable']) {
            $data['monitoring_plan'] = null;
        } elseif (array_key_exists('monitoring_plan', $data) && $data['monitoring_plan'] !== null && $data['monitoring_plan'] !== '') {
            $data['monitoring_plan_not_applicable'] = false;
        }

        $result = $sync->sync(
            $soap,
            $request->user(),
            'soap',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            [...$data, 'last_saved_by' => $request->user()->id],
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        $model = $result['model'];
        abort_unless($model instanceof SoapNote, 500, 'Unexpected model type returned from sync.');

        // Preserves the audit trail the pre-existing SoapController::update()
        // recorded (soap_note.created / soap_note.updated) — SectionSyncService
        // only ever fires a conflict-resolution audit event, so this
        // controller fires the creation/update events itself rather than
        // silently dropping them when the endpoint was rebuilt on the shared
        // sync engine.
        if (in_array($result['status'], ['saved', 'resolved_replaced'], true)) {
            $audit->record($request->user(), $model, $wasNew ? 'soap_note.created' : 'soap_note.updated', [
                'case_id' => $case->id,
                'revision_number' => $model->revision_number,
            ]);
        }

        return response()->json(['section' => $this->payload($model)], $result['httpStatus']);
    }

    /** @return array<string, mixed> */
    private function payload(SoapNote $soap): array
    {
        return [
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
}
