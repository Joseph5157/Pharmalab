<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\RotationProgressService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController extends Controller
{
    public function index(Request $request, RotationProgressService $progress): Response
    {
        return Inertia::render('student/Progress', [
            'assignments' => $progress->forStudent($request->user()),
        ]);
    }
}
