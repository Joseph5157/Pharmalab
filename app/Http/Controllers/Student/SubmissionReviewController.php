<?php

namespace App\Http\Controllers\Student;

use App\Enums\ClinicalActivityType;
use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use App\Services\CaseCompletenessService;
use App\Services\ClinicalCasePresenter;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionReviewController extends Controller
{
    public function show(ClinicalCase $case, ClinicalCasePresenter $presenter, CaseCompletenessService $completeness): Response
    {
        // reviewForSubmission (not submit): once a case is Submitted there is
        // nothing left to review-before-submitting, even though submit()
        // itself now allows a Submitted case through for idempotent retry.
        // See this plan's "Submission status contract" note.
        Gate::authorize('reviewForSubmission', $case);

        $case->loadMissing(['rotationAssignment.rotation', 'clinicalSite', 'ward']);
        $case->load('clinicalActivities');

        return Inertia::render('student/SubmissionReview', [
            'clinicalCase' => $case->only(['id', 'case_number', 'status']),
            'sectionCompletion' => $completeness->sectionCompletion($case),
            'submissionErrors' => $completeness->submissionErrors($case),
            'context' => $presenter->context($case),
            'clinicalProfile' => $presenter->clinicalProfile($case),
            'vitals' => $presenter->vitals($case),
            'investigations' => $presenter->investigations($case),
            'medications' => $presenter->medications($case),
            'soap' => $presenter->soap($case),
            'adr' => $presenter->singletonActivity($case, ClinicalActivityType::Adr),
            'counselling' => $presenter->singletonActivity($case, ClinicalActivityType::Counselling),
            'interventions' => $presenter->repeatableActivity($case, ClinicalActivityType::Intervention),
            'monitoringFollowUps' => $presenter->repeatableActivity($case, ClinicalActivityType::Monitoring),
        ]);
    }
}
