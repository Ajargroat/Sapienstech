<?php

use App\Http\Controllers\Consultant\ConsultantDashboardController;
use App\Http\Controllers\Consultant\ConsultantFeatureController;
use App\Http\Controllers\Consultant\StudentFeatureController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/consultant/dashboard');

Route::prefix('consultant')->name('consultant.')->group(function () {

    // Top navigation: Dashboard | Blog | Direct Chat
    Route::get('/dashboard', [ConsultantDashboardController::class, 'index'])
        ->middleware('consultant.feature:dashboard')
        ->name('dashboard');

    Route::get('/blog', [ConsultantFeatureController::class, 'show'])
        ->defaults('feature', 'blog')
        ->middleware('consultant.feature:blog_management')
        ->name('blog');

    Route::get('/direct-chat', [ConsultantFeatureController::class, 'show'])
        ->defaults('feature', 'direct-chat')
        ->middleware('consultant.feature:direct_chat')
        ->name('direct-chat');

    // Legacy top-level placeholders. No longer reachable from the (removed)
    // sidebar or the new top navigation, but left in place rather than
    // deleted since they are outside the scope of this change.
    Route::get('/permissions', [ConsultantFeatureController::class, 'show'])->defaults('feature', 'permissions')->middleware('consultant.feature:book_access')->name('permissions');
    Route::get('/questions', [ConsultantFeatureController::class, 'show'])->defaults('feature', 'questions')->middleware('consultant.feature:question_management')->name('questions');
    Route::get('/quizzes', [ConsultantFeatureController::class, 'show'])->defaults('feature', 'quizzes')->middleware('consultant.feature:quiz_management')->name('quizzes');

    // Student-centered workspace: everything below belongs to a specific
    // student. {student} is implicitly bound to App\Models\Student, whose
    // BelongsToTenant global scope makes the binding itself tenant-scoped
    // -- a student id from another tenant simply will not resolve (404).
    Route::prefix('students/{student}')->name('student.')->group(function () {
        Route::get('/', [StudentFeatureController::class, 'profile'])
            ->middleware('consultant.feature:student_profile')
            ->name('profile');

        Route::get('/report-card', [StudentFeatureController::class, 'show'])
            ->defaults('feature', 'report-card')
            ->middleware('consultant.feature:report_cards')
            ->name('report-card');

        Route::get('/exams', [StudentFeatureController::class, 'show'])
            ->defaults('feature', 'exams')
            ->middleware('consultant.feature:student_exams')
            ->name('exams');

        Route::get('/schedule', [StudentFeatureController::class, 'show'])
            ->defaults('feature', 'schedule')
            ->middleware('consultant.feature:student_schedule')
            ->name('schedule');

        Route::get('/source-permissions', [StudentFeatureController::class, 'show'])
            ->defaults('feature', 'source-permissions')
            ->middleware('consultant.feature:source_permissions')
            ->name('source-permissions');
    });
});
