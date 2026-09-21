<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use App\Models\SoapNote;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
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
}
