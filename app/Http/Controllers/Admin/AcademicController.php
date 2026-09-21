<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicCohort;
use App\Models\Programme;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AcademicController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Programme::class);

        return Inertia::render('admin/Academic', [
            'programmes' => Programme::query()->with('cohorts')->orderBy('name')->get(),
        ]);
    }

    public function storeProgramme(Request $request, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('create', Programme::class);
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        $institutionId = $request->user()->institution_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:30', Rule::unique('programmes')->where('institution_id', $institutionId)],
            'duration_years' => ['required', 'integer', 'between:1,10'],
        ]);

        DB::transaction(function () use ($request, $audit, $data): void {
            $programme = Programme::query()->create([
                ...$data,
                'code' => strtoupper($data['code']),
                'institution_id' => $request->user()->institution_id,
                'status' => RecordStatus::Active,
            ]);
            $audit->record($request->user(), $programme, 'programme.created', ['code' => $programme->code]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Programme created.']);
    }

    public function storeCohort(Request $request, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('create', AcademicCohort::class);
        $institutionId = $request->user()->institution_id;
        $data = $request->validate([
            'programme_id' => ['required', Rule::exists('programmes', 'id')->where('institution_id', $institutionId)],
            'name' => ['required', 'string', 'max:160'],
            'admission_year' => ['required', 'integer', 'between:2000,'.(now()->year + 1)],
            'academic_year_label' => ['required', 'string', 'max:40'],
        ]);

        $request->validate([
            'name' => [Rule::unique('academic_cohorts')->where(fn ($query) => $query->where('programme_id', $data['programme_id']))],
        ]);

        DB::transaction(function () use ($request, $audit, $data): void {
            $cohort = AcademicCohort::query()->create([
                ...$data,
                'institution_id' => $request->user()->institution_id,
                'status' => RecordStatus::Active,
            ]);
            $audit->record($request->user(), $cohort, 'academic_cohort.created', ['programme_id' => $cohort->programme_id]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Cohort created.']);
    }
}
