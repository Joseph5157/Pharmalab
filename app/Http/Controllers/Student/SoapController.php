<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateSoapNoteRequest;
use App\Models\ClinicalCase;
use App\Models\SoapNote;
use App\Services\AuditTrail;
use App\Services\SectionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SoapController extends Controller
{
    public function show(Request $request, ClinicalCase $case): Response
    {
        Gate::authorize('view', $case);

        $soap = $case->currentSoap;

        return Inertia::render('student/SoapEditor', [
            'clinicalCase' => $case->only(['id', 'status', 'case_number']),
            'soap' => $soap,
        ]);
    }

    public function update(Request $request, ClinicalCase $case, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('update', $case);

        $data = $request->validate([
            'subjective' => ['nullable', 'string'],
            'objective' => ['nullable', 'string'],
            'assessment' => ['nullable', 'string'],
            'plan' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $case, $audit, $data): void {
            $user = $request->user();
            $soap = $case->currentSoap;

            if ($soap === null) {
                $soap = SoapNote::query()->create([
                    'institution_id' => $case->institution_id,
                    'clinical_case_id' => $case->id,
                    'revision_number' => 1,
                    'author_id' => $user->id,
                    'last_saved_by' => $user->id,
                    ...$data,
                ]);

                $audit->record($user, $soap, 'soap_note.created', [
                    'case_id' => $case->id,
                ]);
            } else {
                $soap->update([
                    ...$data,
                    'last_saved_by' => $user->id,
                ]);

                $audit->record($user, $soap, 'soap_note.updated', [
                    'case_id' => $case->id,
                    'revision_number' => $soap->revision_number,
                ]);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'SOAP note saved.']);
    }

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

        $result = $sync->sync(
            $soap,
            $request->user(),
            'soap',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            [...$request->sectionData(), 'last_saved_by' => $request->user()->id],
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
            'monitoring_plan_not_applicable_reason' => $soap->monitoring_plan_not_applicable_reason,
            'drug_related_problem_status' => $soap->drug_related_problem_status,
            'drug_related_problem_categories' => $soap->drug_related_problem_categories,
            'lock_version' => $soap->lock_version,
            'updated_at' => $soap->updated_at->toIso8601String(),
        ];
    }
}
