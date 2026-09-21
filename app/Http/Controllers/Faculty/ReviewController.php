<?php

namespace App\Http\Controllers\Faculty;

use App\Actions\ApproveCase;
use App\Actions\ReturnCase;
use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $cases = ClinicalCase::query()
            ->whereHas('rotationAssignment', fn ($q) => $q->where('primary_preceptor_id', $user->id))
            ->whereIn('status', [CaseStatus::Submitted, CaseStatus::UnderReview, CaseStatus::Returned, CaseStatus::Approved])
            ->with(['student', 'clinicalSite'])
            ->orderByDesc('submitted_at')
            ->get();

        return Inertia::render('faculty/Reviews', [
            'cases' => $cases,
        ]);
    }

    public function show(Request $request, ClinicalCase $case): Response
    {
        Gate::authorize('view', $case);

        $case->load([
            'student',
            'clinicalSite',
            'department',
            'ward',
            'currentSoap',
            'versions.submittedBy',
            'versions.approvedBy',
            'statusTransitions.actor',
        ]);

        return Inertia::render('faculty/CaseReview', [
            'clinicalCase' => $case,
        ]);
    }

    public function approve(Request $request, ClinicalCase $case, ApproveCase $approveCase): RedirectResponse
    {
        Gate::authorize('approve', $case);

        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $approveCase($request->user(), $case, $data['summary'] ?? null);

        return back()->with('toast', ['type' => 'success', 'message' => 'Case approved.']);
    }

    public function returnCase(Request $request, ClinicalCase $case, ReturnCase $returnCase): RedirectResponse
    {
        Gate::authorize('returnCase', $case);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $returnCase($request->user(), $case, $data['reason']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Case returned for correction.']);
    }
}
