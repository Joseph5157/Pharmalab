<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Enums\RotationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicCohort;
use App\Models\ClinicalSite;
use App\Models\Department;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
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

class RotationController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Rotation::class);

        return Inertia::render('admin/Rotations', [
            'rotations' => Rotation::query()
                ->with(['programme', 'cohort', 'clinicalSite', 'department', 'ward', 'assignments.student', 'assignments.faculty'])
                ->orderByDesc('starts_on')
                ->get(),
            'programmes' => Programme::query()->where('status', RecordStatus::Active)->with('cohorts')->orderBy('name')->get(),
            'sites' => ClinicalSite::query()->where('status', RecordStatus::Active)->with(['departments', 'wards'])->orderBy('name')->get(),
            'students' => User::query()->where('role', UserRole::Student)->where('status', UserStatus::Active)->orderBy('name')->get(['id', 'name', 'email']),
            'faculty' => User::query()->where('role', UserRole::Faculty)->where('status', UserStatus::Active)->orderBy('name')->get(['id', 'name', 'email']),
            'statuses' => collect(RotationStatus::cases())->map(fn (RotationStatus $status) => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function store(Request $request, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('create', Rotation::class);
        $institutionId = $request->user()->institution_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'programme_id' => ['required', Rule::exists('programmes', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)->where('status', RecordStatus::Active->value))],
            'academic_cohort_id' => ['nullable', Rule::exists('academic_cohorts', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)->where('status', RecordStatus::Active->value))],
            'clinical_site_id' => ['required', Rule::exists('clinical_sites', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)->where('status', RecordStatus::Active->value))],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)->where('status', RecordStatus::Active->value))],
            'ward_id' => ['nullable', Rule::exists('wards', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)->where('status', RecordStatus::Active->value))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::enum(RotationStatus::class)],
        ]);

        $this->validateHierarchy($data);

        DB::transaction(function () use ($request, $audit, $data): void {
            $rotation = Rotation::query()->create([...$data, 'institution_id' => $request->user()->institution_id]);
            $audit->record($request->user(), $rotation, 'rotation.created', [
                'programme_id' => $rotation->programme_id,
                'clinical_site_id' => $rotation->clinical_site_id,
                'status' => $rotation->status->value,
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Rotation created.']);
    }

    public function updateStatus(Request $request, Rotation $rotation, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('update', $rotation);
        $data = $request->validate(['status' => ['required', Rule::enum(RotationStatus::class)]]);
        $before = $rotation->status->value;

        DB::transaction(function () use ($request, $audit, $data, $rotation, $before): void {
            $rotation->update(['status' => $data['status']]);
            $audit->record($request->user(), $rotation, 'rotation.status_changed', ['from' => $before, 'to' => $rotation->status->value]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Rotation status updated.']);
    }

    public function storeAssignment(Request $request, Rotation $rotation, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('update', $rotation);
        Gate::authorize('create', RotationAssignment::class);
        $institutionId = $request->user()->institution_id;
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)->where('role', UserRole::Student->value)->where('status', UserStatus::Active->value))],
            'faculty_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)->where('role', UserRole::Faculty->value)->where('status', UserStatus::Active->value))],
        ]);

        DB::transaction(function () use ($request, $rotation, $audit, $data): void {
            $assignment = RotationAssignment::query()->updateOrCreate(
                ['rotation_id' => $rotation->id, 'student_id' => $data['student_id']],
                ['institution_id' => $request->user()->institution_id, 'primary_preceptor_id' => $data['faculty_id'], 'status' => RecordStatus::Active],
            );
            $audit->record($request->user(), $assignment, $assignment->wasRecentlyCreated ? 'rotation_assignment.created' : 'rotation_assignment.updated', [
                'rotation_id' => $rotation->id,
                'student_id' => $assignment->student_id,
                'primary_preceptor_id' => $assignment->primary_preceptor_id,
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Rotation assignment saved.']);
    }

    public function updateAssignmentStatus(Request $request, RotationAssignment $rotationAssignment, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('update', $rotationAssignment);
        $data = $request->validate(['status' => ['required', Rule::enum(RecordStatus::class)]]);
        $before = $rotationAssignment->status->value;

        DB::transaction(function () use ($request, $rotationAssignment, $audit, $data, $before): void {
            $rotationAssignment->update(['status' => $data['status']]);
            $audit->record($request->user(), $rotationAssignment, 'rotation_assignment.status_changed', ['from' => $before, 'to' => $rotationAssignment->status->value]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Assignment status updated.']);
    }

    /** @param array<string, mixed> $data */
    private function validateHierarchy(array $data): void
    {
        $errors = [];

        if (($data['academic_cohort_id'] ?? null) !== null && ! AcademicCohort::query()->whereKey($data['academic_cohort_id'])->where('programme_id', $data['programme_id'])->exists()) {
            $errors['academic_cohort_id'] = 'The cohort must belong to the selected programme.';
        }
        if (($data['department_id'] ?? null) !== null && ! Department::query()->whereKey($data['department_id'])->where('clinical_site_id', $data['clinical_site_id'])->exists()) {
            $errors['department_id'] = 'The department must belong to the selected clinical site.';
        }
        if (($data['ward_id'] ?? null) !== null) {
            $ward = Ward::query()->whereKey($data['ward_id'])->first();
            if ($ward === null || $ward->clinical_site_id !== $data['clinical_site_id']) {
                $errors['ward_id'] = 'The ward must belong to the selected clinical site.';
            } elseif (($data['department_id'] ?? null) !== null && $ward->department_id !== $data['department_id']) {
                $errors['ward_id'] = 'The ward must belong to the selected department.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
