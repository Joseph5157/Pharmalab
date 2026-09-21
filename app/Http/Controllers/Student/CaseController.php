<?php

namespace App\Http\Controllers\Student;

use App\Actions\SubmitCase;
use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use App\Models\RotationAssignment;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

    public function store(Request $request, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('create', ClinicalCase::class);

        $institutionId = $request->user()->institution_id;
        $data = $request->validate([
            'rotation_assignment_id' => [
                'required',
                Rule::exists('rotation_assignments', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)->where('student_id', $request->user()->id)->where('status', 'active')),
            ],
            'encounter_date' => ['nullable', 'date'],
            'case_category' => ['nullable', 'string', 'max:80'],
            'age_value' => ['nullable', 'integer', 'min:0', 'max:150'],
            'age_unit' => ['nullable', 'string', 'max:20'],
            'sex' => ['nullable', 'string', 'max:20'],
            'clinical_site_id' => ['nullable', Rule::exists('clinical_sites', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId))],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId))],
            'ward_id' => ['nullable', Rule::exists('wards', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId))],
        ]);

        DB::transaction(function () use ($request, $audit, $data): void {
            $user = $request->user();
            $lastCaseNumber = ClinicalCase::query()
                ->where('student_id', $user->id)
                ->max('case_number') ?? 0;

            $case = ClinicalCase::query()->create([
                ...$data,
                'institution_id' => $user->institution_id,
                'student_id' => $user->id,
                'case_number' => $lastCaseNumber + 1,
                'status' => CaseStatus::Draft,
                'current_revision_number' => 0,
            ]);

            $audit->record($user, $case, 'clinical_case.created', [
                'case_id' => $case->id,
                'case_number' => $case->case_number,
            ]);
        });

        return redirect()->route('student.cases.show', $request->user()->cases()->latest()->first());
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
