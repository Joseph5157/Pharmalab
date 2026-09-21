<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\AcademicController;
use App\Http\Controllers\Admin\ClinicalSiteController;
use App\Http\Controllers\Admin\PeopleController;
use App\Http\Controllers\Admin\RotationController;
use App\Http\Controllers\CaseDraftNoteController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'redirect'])->name('dashboard');

    Route::middleware('role:'.UserRole::Student->value)->group(function (): void {
        Route::get('student', [DashboardController::class, 'student'])->name('student.dashboard');
        Route::get('student/sync-spike', [CaseDraftNoteController::class, 'index'])->name('student.sync-spike');
        Route::get('student/sync-spike/{caseDraftNote}', [CaseDraftNoteController::class, 'show'])->name('student.sync-spike.show');
        Route::put('student/sync-spike/{caseDraftNote}', [CaseDraftNoteController::class, 'sync'])->name('student.sync-spike.sync');
    });

    Route::middleware('role:'.UserRole::Faculty->value)->group(function (): void {
        Route::get('faculty', [DashboardController::class, 'faculty'])->name('faculty.dashboard');
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
