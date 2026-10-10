<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Enums\RecordStatus;
use App\Enums\RotationStatus;
use App\Enums\UserRole;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use App\Services\RotationProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role->dashboardRoute());
    }

    public function student(Request $request, RotationProgressService $progress): Response
    {
        $user = $request->user();
        $active = RotationAssignment::query()
            ->where('student_id', $user->id)
            ->where('status', RecordStatus::Active)
            ->with(['rotation.clinicalSite', 'rotation.ward'])
            ->get()
            ->sortBy(fn (RotationAssignment $assignment) => $assignment->rotation?->starts_on)
            ->values();
        $current = $active->first();
        $rotation = $current?->rotation;
        $entry = $current === null ? null : $progress->forAssignment($current);

        $awaiting = ClinicalCase::query()
            ->where('student_id', $user->id)
            ->whereIn('status', [CaseStatus::Submitted, CaseStatus::UnderReview])
            ->count();

        $rotationMetric = $rotation === null
            ? ['label' => 'Active rotation', 'value' => 'None', 'detail' => 'No active rotation assignment']
            : [
                'label' => 'Active rotation',
                'value' => $rotation->name,
                'detail' => collect([
                    $rotation->ward_id !== null ? $rotation->ward->name : $rotation->clinicalSite->name,
                    $this->daysRemaining($rotation->ends_on),
                    $active->count() > 1 ? '+'.($active->count() - 1).' more' : null,
                ])->filter()->implode(' · '),
            ];

        $casesMetric = match (true) {
            $entry === null => ['label' => 'Cases this rotation', 'value' => '—', 'detail' => 'Available once you are assigned'],
            $entry['overall']['target'] === null => ['label' => 'Cases this rotation', 'value' => (string) $entry['overall']['approved'], 'detail' => 'Approved · no overall target set'],
            default => ['label' => 'Cases this rotation', 'value' => $entry['overall']['approved'].' / '.$entry['overall']['target'], 'detail' => 'Approved cases toward your target'],
        };

        return Inertia::render('student/Dashboard', [
            'metrics' => [
                $rotationMetric,
                $casesMetric,
                ['label' => 'Awaiting feedback', 'value' => (string) $awaiting, 'detail' => $awaiting === 0 ? 'Nothing waiting for review' : 'Waiting for faculty review'],
            ],
        ]);
    }

    public function faculty(Request $request): Response
    {
        $user = $request->user();

        $waiting = ClinicalCase::query()
            ->whereHas('rotationAssignment', fn ($q) => $q->where('primary_preceptor_id', $user->id))
            ->whereIn('status', [CaseStatus::Submitted, CaseStatus::UnderReview])
            ->count();

        $active = RotationAssignment::query()
            ->where('primary_preceptor_id', $user->id)
            ->where('status', RecordStatus::Active)
            ->with('rotation:id,name')
            ->get();
        $students = $active->pluck('student_id')->unique()->count();
        $rotationNames = $active->pluck('rotation.name')->filter()->unique()->values();

        return Inertia::render('faculty/Dashboard', [
            'metrics' => [
                ['label' => 'Waiting for review', 'value' => (string) $waiting, 'detail' => $waiting === 0 ? 'Your queue is clear' : 'Submitted cases to review'],
                [
                    'label' => 'Assigned students',
                    'value' => (string) $students,
                    'detail' => $rotationNames->isEmpty() ? 'No active rotation assignments' : $rotationNames->take(2)->implode(', ').($rotationNames->count() > 2 ? ' +'.($rotationNames->count() - 2).' more' : ''),
                ],
                ['label' => 'Median turnaround', 'value' => '—', 'detail' => 'Not yet measured'],
            ],
        ]);
    }

    public function administrator(): Response
    {
        $readyParts = [
            Programme::query()->exists(),
            ClinicalSite::query()->exists(),
            User::query()->where('role', UserRole::Student)->exists(),
            User::query()->where('role', UserRole::Faculty)->exists(),
            Rotation::query()->exists(),
            RotationAssignment::query()->exists(),
        ];
        $readiness = (int) round((count(array_filter($readyParts)) / count($readyParts)) * 100);

        return Inertia::render('admin/Dashboard', [
            'metrics' => [
                ['label' => 'Active rotations', 'value' => (string) Rotation::query()->where('status', RotationStatus::Active)->count(), 'detail' => 'Currently open placements'],
                ['label' => 'Students & faculty', 'value' => (string) User::query()->whereIn('role', [UserRole::Student, UserRole::Faculty])->count(), 'detail' => 'Institution accounts'],
                ['label' => 'Setup readiness', 'value' => $readiness.'%', 'detail' => 'Six essentials for the walking skeleton'],
            ],
        ]);
    }

    private function daysRemaining(\DateTimeInterface $endsOn): string
    {
        $days = (int) now()->startOfDay()->diffInDays($endsOn, false);

        return match (true) {
            $days < 0 => 'Ended',
            $days === 0 => 'Ends today',
            $days === 1 => '1 day remaining',
            default => $days.' days remaining',
        };
    }
}
