<?php

namespace App\Http\Controllers\Student;

use App\Actions\SubmitCase;
use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SubmissionController extends Controller
{
    public function store(Request $request, ClinicalCase $case, SubmitCase $submitCase): RedirectResponse
    {
        Gate::authorize('submit', $case);

        $submitCase($request->user(), $case);

        return back()->with('toast', ['type' => 'success', 'message' => 'Case submitted for review.']);
    }
}
