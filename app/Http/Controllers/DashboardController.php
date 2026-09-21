<?php

namespace App\Http\Controllers;

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
        return Inertia::render('admin/Dashboard', [
            'metrics' => [
                ['label' => 'Active rotations', 'value' => '1', 'detail' => 'Pilot foundation rotation'],
                ['label' => 'People', 'value' => '3', 'detail' => '1 student · 1 faculty · 1 admin'],
                ['label' => 'Setup readiness', 'value' => '60%', 'detail' => 'Clinical forms await validation'],
            ],
        ]);
    }
}
