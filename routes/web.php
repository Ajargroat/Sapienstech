<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\StudentLoginController;
use App\Http\Controllers\Consultant\ConsultantDashboardController;
use App\Http\Controllers\Consultant\ConsultantFeatureController;
use App\Http\Controllers\Consultant\Bulk\BulkExamController;
use App\Http\Controllers\Consultant\Bulk\BulkHistoryController;
use App\Http\Controllers\Consultant\Bulk\BulkScheduleController;
use App\Http\Controllers\Consultant\Settings\AppearanceController;
use App\Http\Controllers\Consultant\Settings\BlogController as ConsultantBlogController;
use App\Http\Controllers\Consultant\Settings\ProfileController as ConsultantProfileController;
use App\Http\Controllers\Consultant\StudentExamController;
use App\Http\Controllers\Consultant\StudentFeatureController;
use App\Http\Controllers\Consultant\StudentReportCardController;
use App\Http\Controllers\Consultant\StudentScheduleController;
use App\Http\Controllers\Public\BlogController as PublicBlogController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Student\Settings\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Support\SettingsTabs;
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
| Public blog — tenant-published posts, themed like the landing page.
|--------------------------------------------------------------------------
*/
Route::get('/blog', [PublicBlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [PublicBlogController::class, 'show'])->name('blog.show');

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

    /*
    | Blog management — the settings-hub Blog tab. Gated by the same
    | blog_management flag as the legacy placeholder route above.
    */
    Route::prefix('settings/blog')->name('settings.blog.')->middleware('consultant.feature:blog_management')->group(function () {
        Route::get('/', [ConsultantBlogController::class, 'index'])->name('index');
        Route::get('/create', [ConsultantBlogController::class, 'create'])->name('create');
        Route::post('/', [ConsultantBlogController::class, 'store'])->name('store');
        Route::put('/landing', [ConsultantBlogController::class, 'setLandingSource'])->name('landing');
        Route::get('/{post}/edit', [ConsultantBlogController::class, 'edit'])->name('edit');
        Route::patch('/{post}', [ConsultantBlogController::class, 'update'])->name('update');
        Route::delete('/{post}', [ConsultantBlogController::class, 'destroy'])->name('destroy');
    });

    /*
    | Settings hub — reached from the topnav user dropdown. Each tab is its
    | own route so feature gating and shareable URLs stay independent.
    */
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::redirect('/', '/consultant/settings/profile');

        Route::get('profile', [ConsultantProfileController::class, 'index'])
            ->middleware('consultant.feature:settings_profile')
            ->name('profile');
        Route::patch('profile', [ConsultantProfileController::class, 'update'])
            ->middleware('consultant.feature:settings_profile')
            ->name('profile.update');
        Route::put('profile/password', [ConsultantProfileController::class, 'updatePassword'])
            ->middleware('consultant.feature:settings_profile')
            ->name('profile.password');
        Route::put('profile/avatar', [ConsultantProfileController::class, 'updateAvatar'])
            ->middleware('consultant.feature:settings_profile')
            ->name('profile.avatar');
        Route::delete('profile/avatar', [ConsultantProfileController::class, 'deleteAvatar'])
            ->middleware('consultant.feature:settings_profile')
            ->name('profile.avatar.delete');

        Route::get('chat', fn () => view('consultant.settings.chat', [
            'tabs' => SettingsTabs::visible('consultant'),
            'activeTab' => 'chat',
        ]))
            ->middleware('consultant.feature:settings_chat')
            ->name('chat');

        /*
        | Appearance studio — schema-driven editor over the tenant's runtime
        | config layer (see config/studio.php). "For everyone" is gated to the
        | tenant admin inside the controller; the flag only hides the tab.
        */
        Route::prefix('appearance')->middleware('consultant.feature:theme_studio')->group(function () {
            Route::get('/', [AppearanceController::class, 'index'])->name('appearance');
            Route::post('/', [AppearanceController::class, 'save'])->name('appearance.save');
            Route::post('reset', [AppearanceController::class, 'resetKey'])->name('appearance.reset');
            Route::post('reset-all', [AppearanceController::class, 'resetAll'])->name('appearance.reset.all');
            Route::post('preview/exit', [AppearanceController::class, 'exitPreview'])->name('appearance.preview.exit');
            Route::post('live', [AppearanceController::class, 'live'])->name('appearance.live');
        });
    });

    /*
    | Bulk Actions ("اقدامات گروهی") — assign an exam or a weekly schedule
    | block to many students at once, by checkbox selection or by re-applying
    | a filter set server-side, with a revertable history.
    */
    Route::prefix('bulk')->name('bulk.')->middleware('consultant.feature:bulk_actions')->group(function () {
        Route::get('exams', [BulkExamController::class, 'create'])->name('exams');
        Route::post('exams', [BulkExamController::class, 'store'])->name('exams.store');
        Route::get('schedule', [BulkScheduleController::class, 'create'])->name('schedule');
        Route::post('schedule', [BulkScheduleController::class, 'store'])->name('schedule.store');
        Route::get('history', [BulkHistoryController::class, 'index'])->name('history');
        Route::delete('history/{action}', [BulkHistoryController::class, 'revert'])->name('history.revert');
    });

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

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::redirect('/', '/student/settings/profile');

        Route::get('profile', [StudentProfileController::class, 'index'])->name('profile');
        Route::patch('profile', [StudentProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [StudentProfileController::class, 'updatePassword'])->name('profile.password');
        Route::put('profile/avatar', [StudentProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::delete('profile/avatar', [StudentProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
    });
});
