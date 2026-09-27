<?php

namespace App\Http\Controllers\Student;

use App\Actions\SubmitCase;
use App\Exceptions\CaseNotReadyForSubmissionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreSubmissionRequest;
use App\Models\ClinicalCase;
use Illuminate\Http\RedirectResponse;

class SubmissionController extends Controller
{
    public function store(StoreSubmissionRequest $request, ClinicalCase $case, SubmitCase $submitCase): RedirectResponse
    {
        try {
            $submitCase($request->user(), $case, $request->boolean('deidentification_attested'));
        } catch (CaseNotReadyForSubmissionException $e) {
            return redirect()
                ->route('student.cases.submission-review', $case)
                ->with('toast', ['type' => 'error', 'message' => 'This case is not ready to submit.'])
                ->withErrors(['submission' => collect($e->errors())->pluck('message')->all()]);
        }

        return redirect()->route('student.cases.show', $case)
            ->with('toast', ['type' => 'success', 'message' => 'Case submitted for review.']);
    }
}
