<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\AcademicController;
use App\Http\Controllers\Admin\ClinicalSiteController;
use App\Http\Controllers\Admin\PeopleController;
use App\Http\Controllers\Admin\RotationController;
use App\Http\Controllers\CaseDraftNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Faculty\ReviewController;
use App\Http\Controllers\Student\CaseController;
use App\Http\Controllers\Student\CaseClinicalProfileController;
use App\Http\Controllers\Student\CaseContextController;
use App\Http\Controllers\Student\CaseEditorController;
use App\Http\Controllers\Student\PortfolioController;
use App\Http\Controllers\Student\SoapController;
use App\Http\Controllers\Student\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'redirect'])->name('dashboard');

    Route::middleware('role:'.UserRole::Student->value)->group(function (): void {
        Route::get('student', [DashboardController::class, 'student'])->name('student.dashboard');
        Route::get('student/sync-spike', [CaseDraftNoteController::class, 'index'])->name('student.sync-spike');
        Route::get('student/sync-spike/{caseDraftNote}', [CaseDraftNoteController::class, 'show'])->name('student.sync-spike.show');
        Route::put('student/sync-spike/{caseDraftNote}', [CaseDraftNoteController::class, 'sync'])->name('student.sync-spike.sync');

        Route::get('student/cases', [CaseController::class, 'index'])->name('student.cases.index');
        Route::post('student/cases', [CaseController::class, 'store'])->name('student.cases.store');
        Route::get('student/cases/{case}', [CaseController::class, 'show'])->name('student.cases.show');
        Route::put('student/cases/{case}/context', [CaseContextController::class, 'sync'])->name('student.cases.context.sync');
        Route::put('student/cases/{case}/clinical-profile', [CaseClinicalProfileController::class, 'sync'])->name('student.cases.clinical-profile.sync');
        Route::get('student/cases/{case}/edit', [CaseEditorController::class, 'show'])->name('student.cases.edit');
        Route::get('student/cases/{case}/soap', [SoapController::class, 'show'])->name('student.cases.soap');
        Route::put('student/cases/{case}/soap', [SoapController::class, 'update'])->name('student.cases.soap.update');
        Route::post('student/cases/{case}/submit', [SubmissionController::class, 'store'])->name('student.cases.submit');
        Route::get('student/portfolio', [PortfolioController::class, 'index'])->name('student.portfolio');
    });

    Route::middleware('role:'.UserRole::Faculty->value)->group(function (): void {
        Route::get('faculty', [DashboardController::class, 'faculty'])->name('faculty.dashboard');
        Route::get('faculty/reviews', [ReviewController::class, 'index'])->name('faculty.reviews.index');
        Route::get('faculty/reviews/{case}', [ReviewController::class, 'show'])->name('faculty.reviews.show');
        Route::post('faculty/reviews/{case}/approve', [ReviewController::class, 'approve'])->name('faculty.reviews.approve');
        Route::post('faculty/reviews/{case}/return', [ReviewController::class, 'returnCase'])->name('faculty.reviews.return');
    });

    Route::prefix('admin')->middleware('role:'.UserRole::Administrator->value)->group(function (): void {
        Route::get('/', [DashboardController::class, 'administrator'])->name('admin.dashboard');
        Route::get('academic', [AcademicController::class, 'index'])->name('admin.academic.index');
        Route::post('programmes', [AcademicController::class, 'storeProgramme'])->name('admin.programmes.store');
        Route::post('cohorts', [AcademicController::class, 'storeCohort'])->name('admin.cohorts.store');
        Route::get('clinical-sites', [ClinicalSiteController::class, 'index'])->name('admin.clinical-sites.index');
        Route::post('clinical-sites', [ClinicalSiteController::class, 'storeSite'])->name('admin.clinical-sites.store');
        Route::post('departments', [ClinicalSiteController::class, 'storeDepartment'])->name('admin.departments.store');
        Route::post('wards', [ClinicalSiteController::class, 'storeWard'])->name('admin.wards.store');
        Route::get('people', [PeopleController::class, 'index'])->name('admin.people.index');
        Route::post('people', [PeopleController::class, 'store'])->name('admin.people.store');
        Route::patch('people/{user}/status', [PeopleController::class, 'updateStatus'])->name('admin.people.status');
        Route::get('rotations', [RotationController::class, 'index'])->name('admin.rotations.index');
        Route::post('rotations', [RotationController::class, 'store'])->name('admin.rotations.store');
        Route::patch('rotations/{rotation}/status', [RotationController::class, 'updateStatus'])->name('admin.rotations.status');
        Route::post('rotations/{rotation}/assignments', [RotationController::class, 'storeAssignment'])->name('admin.rotation-assignments.store');
        Route::patch('rotation-assignments/{rotationAssignment}/status', [RotationController::class, 'updateAssignmentStatus'])->name('admin.rotation-assignments.status');
    });
});

require __DIR__.'/settings.php';
