<?php

namespace App\Http\Controllers\Student;

use App\Enums\CaseFormVersion;
use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreClinicalCaseRequest;
use App\Models\ClinicalCase;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CaseController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ClinicalCase::class);

        $cases = ClinicalCase::query()
            ->where('student_id', $request->user()->id)
            ->with(['clinicalSite', 'department', 'ward'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('student/Cases', [
            'cases' => $cases,
        ]);
    }

    public function store(StoreClinicalCaseRequest $request, AuditTrail $audit): RedirectResponse
    {
        $data = $request->validated();

        $case = DB::transaction(function () use ($request, $audit, $data): ClinicalCase {
            $user = $request->user();
            $lastCaseNumber = ClinicalCase::query()
                ->where('student_id', $user->id)
                ->max('case_number') ?? 0;

            $case = new ClinicalCase($data);
            $case->forceFill([
                'institution_id' => $user->institution_id,
                'student_id' => $user->id,
                'case_number' => $lastCaseNumber + 1,
                'status' => CaseStatus::Draft,
                'current_revision_number' => 0,
                'form_version' => CaseFormVersion::PharmdV1->value,
            ]);
            $case->save();

            $audit->record($user, $case, 'clinical_case.created', [
                'case_id' => $case->id,
                'case_number' => $case->case_number,
            ]);

            return $case;
        });

        return redirect()->route('student.cases.show', $case);
    }

    public function show(Request $request, ClinicalCase $case): Response
    {
        Gate::authorize('view', $case);

        $case->load(['clinicalSite', 'department', 'ward', 'currentSoap', 'versions']);

        return Inertia::render('student/CaseShow', [
            'clinicalCase' => $case,
        ]);
    }
}
