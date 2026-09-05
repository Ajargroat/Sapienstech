<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\StudentLoginController;
use App\Http\Controllers\Consultant\ConsultantDashboardController;
use App\Http\Controllers\Consultant\ConsultantFeatureController;
use App\Http\Controllers\Consultant\StudentExamController;
use App\Http\Controllers\Consultant\StudentFeatureController;
use App\Http\Controllers\Consultant\StudentReportCardController;
use App\Http\Controllers\Consultant\StudentScheduleController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Student\StudentDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website — tenant-themed, no auth required.
|--------------------------------------------------------------------------
*/
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');

/*
|--------------------------------------------------------------------------
| Authentication — single page with consultant/student role tabs.
| The page lives at /login; each tab posts to its own guard endpoint.
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:login');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Consultant area — authenticated tenant users only.
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('consultant')->name('consultant.')->group(function () {

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

    Route::get('/permissions', [ConsultantFeatureController::class, 'show'])->defaults('feature', 'permissions')->middleware('consultant.feature:book_access')->name('permissions');
    Route::get('/questions', [ConsultantFeatureController::class, 'show'])->defaults('feature', 'questions')->middleware('consultant.feature:question_management')->name('questions');
    Route::get('/quizzes', [ConsultantFeatureController::class, 'show'])->defaults('feature', 'quizzes')->middleware('consultant.feature:quiz_management')->name('quizzes');

    Route::prefix('students/{student}')->name('student.')->group(function () {

        Route::get('/', [StudentFeatureController::class, 'profile'])
            ->middleware('consultant.feature:student_profile')
            ->name('profile');

        Route::get('/report-card', [StudentReportCardController::class, 'index'])
            ->middleware('consultant.feature:report_cards')
            ->name('report-card');

        Route::get('/exams', [StudentExamController::class, 'index'])
            ->middleware('consultant.feature:student_exams')
            ->name('exams');

        Route::post('/exams', [StudentExamController::class, 'store'])
            ->middleware('consultant.feature:student_exams')
            ->name('exams.store');

        Route::get('/exams/questions', [StudentExamController::class, 'questions'])
            ->middleware('consultant.feature:student_exams')
            ->name('exams.questions');

        Route::get('/exams/{assignment}/run', [StudentExamController::class, 'run'])
            ->middleware('consultant.feature:student_exams')
            ->name('exams.run');

        Route::post('/exams/{assignment}/attempt', [StudentExamController::class, 'storeAttempt'])
            ->middleware('consultant.feature:student_exams')
            ->name('exams.attempt');

        Route::get('/exams/{assignment}/result', [StudentExamController::class, 'result'])
            ->middleware('consultant.feature:student_exams')
            ->name('exams.result');

        Route::get('/schedule', [StudentScheduleController::class, 'edit'])
            ->middleware('consultant.feature:student_schedule')
            ->name('schedule');

        Route::prefix('schedule/items')->name('schedule.items.')->middleware('consultant.feature:student_schedule')->group(function () {
            Route::get('/', [StudentScheduleController::class, 'items'])->name('index');
            Route::post('/', [StudentScheduleController::class, 'store'])->name('store');
            Route::put('/{item}', [StudentScheduleController::class, 'update'])->name('update');
            Route::delete('/{item}', [StudentScheduleController::class, 'destroy'])->name('destroy');
            Route::get('/{item}/comments', [StudentScheduleController::class, 'comments'])->name('comments');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Student Portal (Isolated from Consultant Dashboard)
| Uses the 'student' auth guard defined in config/auth.php
|--------------------------------------------------------------------------
*/
Route::middleware('guest:student')->prefix('student')->name('student.')->group(function () {
    // GET is kept only so existing redirects (guest student, logout) resolve;
    // it forwards to the shared /login page with the student tab pre-selected.
    Route::get('login', [StudentLoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [StudentLoginController::class, 'login'])->middleware('throttle:login');
});

Route::middleware('auth:student')->prefix('student')->name('student.')->group(function () {
    Route::post('logout', [StudentLoginController::class, 'logout'])->name('logout');
    Route::get('dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
});
