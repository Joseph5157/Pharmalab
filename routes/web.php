<?php

use App\Enums\UserRole;
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
    });
});

require __DIR__.'/settings.php';
