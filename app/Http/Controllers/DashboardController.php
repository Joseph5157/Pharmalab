<?php

namespace App\Http\Controllers;

use App\Enums\RotationStatus;
use App\Enums\UserRole;
use App\Models\ClinicalSite;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
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

    public function student(): Response
    {
        return Inertia::render('student/Dashboard', [
            'metrics' => [
                ['label' => 'Active rotation', 'value' => 'Foundation Medicine', 'detail' => 'Ward 3 · 12 days remaining'],
                ['label' => 'Cases this rotation', 'value' => '0 / 6', 'detail' => 'Create your first learning case'],
                ['label' => 'Awaiting feedback', 'value' => '0', 'detail' => 'Nothing waiting for review'],
            ],
        ]);
    }

    public function faculty(): Response
    {
        return Inertia::render('faculty/Dashboard', [
            'metrics' => [
                ['label' => 'Waiting for review', 'value' => '0', 'detail' => 'Your queue is clear'],
                ['label' => 'Assigned students', 'value' => '1', 'detail' => 'Foundation Medicine'],
                ['label' => 'Median turnaround', 'value' => '—', 'detail' => 'Available after the first review'],
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
}
