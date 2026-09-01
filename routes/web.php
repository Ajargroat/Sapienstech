<?php

use App\Http\Controllers\Consultant\ConsultantDashboardController;
use App\Http\Controllers\Consultant\ConsultantFeatureController;
use App\Http\Controllers\Consultant\StudentExamController;
use App\Http\Controllers\Consultant\StudentFeatureController;
use App\Http\Controllers\Consultant\StudentReportCardController;
use App\Http\Controllers\Consultant\StudentScheduleController;
use App\Http\Controllers\Consultant\StudentSourcePermissionController;
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

            // Prototype report-card workspace (sample data in the controller until a
            // ReportCard model exists). Kept under the same route name so existing
            // links (student profile) keep working unchanged.
            Route::get('/report-card', [StudentReportCardController::class, 'index'])
                ->middleware('consultant.feature:report_cards')
                ->name('report-card');


            // Prototype exams workspace (sample data in the controller until an
            // Exam model exists). Kept under the same route name so existing
            // links (student profile) keep working unchanged.
            Route::get('/exams', [StudentExamController::class, 'index'])
                ->middleware('consultant.feature:student_exams')
                ->name('exams');


        // Real schedule editor (replaces the old placeholder page). Kept
        // under the same route name, `consultant.student.schedule`, so any
        // existing links (e.g. the dashboard's Actions menu) keep working
        // unchanged.
        Route::get('/schedule', [StudentScheduleController::class, 'edit'])
            ->middleware('consultant.feature:student_schedule')
            ->name('schedule');

        // JSON API backing the schedule editor's calendar. Gated behind the
        // same student_schedule feature flag as the page itself.
        Route::prefix('schedule/items')->name('schedule.items.')->middleware('consultant.feature:student_schedule')->group(function () {
            Route::get('/', [StudentScheduleController::class, 'items'])->name('index');
            Route::post('/', [StudentScheduleController::class, 'store'])->name('store');
            Route::put('/{item}', [StudentScheduleController::class, 'update'])->name('update');
            Route::delete('/{item}', [StudentScheduleController::class, 'destroy'])->name('destroy');
            Route::get('/{item}/comments', [StudentScheduleController::class, 'comments'])->name('comments');
        });

        // Prototype source-permissions workspace (sample data in the controller
        // until Source/Grant models exist). Kept under the same route name so
        // existing links (student profile) keep working unchanged.
        Route::get('/source-permissions', [StudentSourcePermissionController::class, 'index'])
            ->middleware('consultant.feature:source_permissions')
            ->name('source-permissions');

    });
});
