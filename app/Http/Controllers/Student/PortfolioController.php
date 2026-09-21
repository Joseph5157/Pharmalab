<?php

namespace App\Http\Controllers\Student;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function index(Request $request): Response
    {
        $cases = ClinicalCase::query()
            ->where('student_id', $request->user()->id)
            ->where('status', CaseStatus::Approved)
            ->with(['clinicalSite', 'department', 'ward', 'versions.approvedBy'])
            ->orderByDesc('approved_at')
            ->get();

        return Inertia::render('student/Portfolio', [
            'cases' => $cases,
        ]);
    }
}
