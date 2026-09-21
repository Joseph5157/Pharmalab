<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Models\ClinicalSite;
use App\Models\Department;
use App\Models\Ward;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClinicalSiteController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ClinicalSite::class);

        return Inertia::render('admin/ClinicalSites', [
            'sites' => ClinicalSite::query()->with(['departments', 'wards.department'])->orderBy('name')->get(),
        ]);
    }

    public function storeSite(Request $request, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('create', ClinicalSite::class);
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        $institutionId = $request->user()->institution_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => ['required', 'string', 'max:30', Rule::unique('clinical_sites')->where('institution_id', $institutionId)],
        ]);

        DB::transaction(function () use ($request, $audit, $data): void {
            $site = ClinicalSite::query()->create([
                ...$data,
                'code' => strtoupper($data['code']),
                'institution_id' => $request->user()->institution_id,
                'status' => RecordStatus::Active,
            ]);
            $audit->record($request->user(), $site, 'clinical_site.created', ['code' => $site->code]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Clinical site created.']);
    }

    public function storeDepartment(Request $request, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('create', Department::class);
        $institutionId = $request->user()->institution_id;
        $data = $request->validate([
            'clinical_site_id' => ['required', Rule::exists('clinical_sites', 'id')->where('institution_id', $institutionId)],
            'name' => ['required', 'string', 'max:160'],
        ]);
        $request->validate([
            'name' => [Rule::unique('departments')->where(fn ($query) => $query->where('clinical_site_id', $data['clinical_site_id']))],
        ]);

        DB::transaction(function () use ($request, $audit, $data): void {
            $department = Department::query()->create([
                ...$data,
                'institution_id' => $request->user()->institution_id,
                'status' => RecordStatus::Active,
            ]);
            $audit->record($request->user(), $department, 'department.created', ['clinical_site_id' => $department->clinical_site_id]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Department created.']);
    }

    public function storeWard(Request $request, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('create', Ward::class);
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        $institutionId = $request->user()->institution_id;
        $data = $request->validate([
            'clinical_site_id' => ['required', Rule::exists('clinical_sites', 'id')->where('institution_id', $institutionId)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('institution_id', $institutionId)],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:30'],
        ]);

        if (($data['department_id'] ?? null) !== null && ! Department::query()->whereKey($data['department_id'])->where('clinical_site_id', $data['clinical_site_id'])->exists()) {
            throw ValidationException::withMessages(['department_id' => 'The department must belong to the selected clinical site.']);
        }

        $request->validate([
            'code' => [Rule::unique('wards')->where(fn ($query) => $query->where('clinical_site_id', $data['clinical_site_id']))],
        ]);

        DB::transaction(function () use ($request, $audit, $data): void {
            $ward = Ward::query()->create([
                ...$data,
                'code' => strtoupper($data['code']),
                'institution_id' => $request->user()->institution_id,
                'status' => RecordStatus::Active,
            ]);
            $audit->record($request->user(), $ward, 'ward.created', ['clinical_site_id' => $ward->clinical_site_id, 'department_id' => $ward->department_id]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Ward created.']);
    }
}
