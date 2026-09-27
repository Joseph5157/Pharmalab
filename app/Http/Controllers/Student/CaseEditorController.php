<?php

namespace App\Http\Controllers\Student;

use App\Enums\ClinicalActivityType;
use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use App\Services\ClinicalCasePresenter;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CaseEditorController extends Controller
{
    public function show(ClinicalCase $case, ClinicalCasePresenter $presenter): Response
    {
        Gate::authorize('update', $case);

        $case->loadMissing(['rotationAssignment.rotation', 'clinicalSite', 'ward']);
        $case->load('clinicalActivities');

        return Inertia::render('student/CaseEditor', [
            'clinicalCase' => $case->only(['id', 'case_number', 'status']),
            'userId' => request()->user()->id,
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
            'initialSection' => request()->query('section'),
        ]);
    }
}
