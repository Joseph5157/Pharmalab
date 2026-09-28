<?php

namespace App\Http\Controllers\Faculty;

use App\Actions\ApproveCase;
use App\Actions\ReopenCase;
use App\Actions\ReturnCase;
use App\Enums\CaseReviewSection;
use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'versions.statusTransitions.reviewComments.author',
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
            'section_comments' => ['sometimes', 'array'],
            'section_comments.*.section' => ['required', Rule::enum(CaseReviewSection::class)],
            'section_comments.*.body' => ['required', 'string', 'max:2000'],
        ]);

        $sectionComments = $data['section_comments'] ?? [];
        $this->assertDistinctSections($sectionComments);

        $approveCase($request->user(), $case, $data['summary'] ?? null, $sectionComments);

        return back()->with('toast', ['type' => 'success', 'message' => 'Case approved.']);
    }

    public function returnCase(Request $request, ClinicalCase $case, ReturnCase $returnCase): RedirectResponse
    {
        Gate::authorize('returnCase', $case);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'section_comments' => ['required', 'array', 'min:1'],
            'section_comments.*.section' => ['required', Rule::enum(CaseReviewSection::class)],
            'section_comments.*.body' => ['required', 'string', 'max:2000'],
            'section_comments.*.is_flagged' => ['required', 'boolean'],
        ]);

        $this->assertDistinctSections($data['section_comments']);

        if (! in_array(true, array_column($data['section_comments'], 'is_flagged'), true)) {
            throw ValidationException::withMessages([
                'section_comments' => 'At least one section must be flagged to return a case.',
            ]);
        }

        $returnCase($request->user(), $case, $data['reason'], $data['section_comments']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Case returned for correction.']);
    }

    public function reopen(Request $request, ClinicalCase $case, ReopenCase $reopenCase): RedirectResponse
    {
        Gate::authorize('reopen', $case);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $reopenCase($request->user(), $case, $data['reason']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Case reopened.']);
    }

    /** @param array<int, array{section: string}> $sectionComments */
    private function assertDistinctSections(array $sectionComments): void
    {
        $sections = array_column($sectionComments, 'section');

        if (count($sections) !== count(array_unique($sections))) {
            throw ValidationException::withMessages([
                'section_comments' => 'Each section can only receive one comment per review.',
            ]);
        }
    }
}
