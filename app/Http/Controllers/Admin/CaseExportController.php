<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use App\Services\CaseExportPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CaseExportController extends Controller
{
    public function export(Request $request, ClinicalCase $case, CaseExportPdfService $pdf): Response
    {
        Gate::authorize('exportPdf', $case);

        return $pdf->export($request->user(), $case);
    }
}
