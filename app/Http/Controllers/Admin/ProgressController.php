<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use App\Models\Rotation;
use App\Services\RotationProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController extends Controller
{
    public function index(Request $request, RotationProgressService $progress): Response|RedirectResponse
    {
        $institutionId = $request->user()->institution_id;

        // An unknown or another institution's rotation id is a validation
        // error, never a silently empty report. Redirect to the unfiltered
        // page (not back()), so a bad bookmarked URL cannot redirect to itself.
        $validator = Validator::make($request->query(), [
            'rotation' => ['nullable', 'string', Rule::exists('rotations', 'id')->where('institution_id', $institutionId)],
        ]);
        if ($validator->fails()) {
            return redirect()->route('admin.progress')->withErrors($validator);
        }
        $rotationId = $validator->validated()['rotation'] ?? null;

        $approvedCases = $rotationId === null ? [] : ClinicalCase::query()
            ->where('institution_id', $institutionId)
            ->where('status', CaseStatus::Approved)
            ->whereHas('rotationAssignment', fn ($q) => $q->where('rotation_id', $rotationId))
            ->with('student:id,name')
            ->orderByDesc('approved_at')
            ->get()
            ->map(fn (ClinicalCase $case): array => [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'student_name' => $case->student?->name,
                'case_category' => $case->case_category,
                'approved_at' => $case->approved_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('admin/Progress', [
            'rotations' => Rotation::query()
                ->where('institution_id', $institutionId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Rotation $rotation): array => ['id' => $rotation->id, 'name' => $rotation->name])
                ->all(),
            'selectedRotationId' => $rotationId,
            'assignments' => $progress->forInstitution($institutionId, $rotationId),
            'approvedCases' => $approvedCases,
        ]);
    }
}
