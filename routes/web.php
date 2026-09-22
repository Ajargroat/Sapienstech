<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\StudentLoginController;
use App\Http\Controllers\Consultant\ChatController as ConsultantChatController;
use App\Http\Controllers\Consultant\ConsultantDashboardController;
use App\Http\Controllers\Consultant\ConsultantFeatureController;
use App\Http\Controllers\Consultant\DealController as ConsultantDealController;
use App\Http\Controllers\Consultant\Settings\ChatSettingsController as ConsultantChatSettingsController;
use App\Http\Controllers\Consultant\Bulk\BulkExamController;
use App\Http\Controllers\Consultant\Bulk\BulkHistoryController;
use App\Http\Controllers\Consultant\Bulk\BulkScheduleController;
use App\Http\Controllers\Consultant\Settings\AppearanceController;
use App\Http\Controllers\Consultant\BlogController as ConsultantBlogController;
use App\Http\Controllers\Consultant\Settings\ProfileController as ConsultantProfileController;
use App\Http\Controllers\Consultant\StudentExamController;
use App\Http\Controllers\Consultant\StudentFeatureController;
use App\Http\Controllers\Consultant\StudentReportCardController;
use App\Http\Controllers\Consultant\StudentScheduleController;
use App\Http\Controllers\Public\BlogController as PublicBlogController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Student\ChatController as StudentChatController;
use App\Http\Controllers\Student\DealController as StudentDealController;
use App\Http\Controllers\Student\Settings\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\StudentAssignmentController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\StudentExamController as StudentExamPortalController;
use App\Http\Controllers\Student\StudentMaterialController;
use App\Http\Controllers\Student\StudentReportCardController as StudentReportCardPortalController;
use App\Http\Controllers\Student\StudentTimetableController;
use App\Http\Controllers\Teacher\TeacherAssignmentController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
use App\Http\Controllers\Teacher\TeacherMaterialController;
use App\Http\Controllers\Teacher\TeacherScheduleController;
use App\Http\Controllers\Teacher\TeacherStudentController;
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

    /*
    | Blog management — a first-class top-nav section (dashboard | blog |
    | direct chat). Gated by the blog_management flag.
    */
    Route::prefix('blog')->name('blog.')->middleware('consultant.feature:blog_management')->group(function () {
        Route::get('/', [ConsultantBlogController::class, 'index'])->name('index');
        Route::put('/reorder', [ConsultantBlogController::class, 'reorder'])->name('reorder');
        Route::get('/create', [ConsultantBlogController::class, 'create'])->name('create');
        Route::post('/', [ConsultantBlogController::class, 'store'])->name('store');
        Route::post('/media', [ConsultantBlogController::class, 'media'])->name('media');
        Route::get('/{post}/edit', [ConsultantBlogController::class, 'edit'])->name('edit');
        Route::patch('/{post}', [ConsultantBlogController::class, 'update'])->name('update');
        Route::delete('/{post}', [ConsultantBlogController::class, 'destroy'])->name('destroy');
    });

    /*
    | Direct chat — realtime threads + consultant-created group rooms.
    | Everything (page and JSON) sits behind the tenant-resolved
    | `direct_chat` flag; see App\Http\Controllers\Consultant\ChatController.
    */
    Route::prefix('direct-chat')->name('direct-chat.')->middleware('consultant.feature:direct_chat')->group(function () {
        Route::get('students', [ConsultantChatController::class, 'students'])->name('students');
        Route::get('conversations', [ConsultantChatController::class, 'conversations'])->name('conversations');
        Route::post('conversations', [ConsultantChatController::class, 'store'])->name('conversations.store');
        Route::post('groups', [ConsultantChatController::class, 'storeGroup'])->name('groups.store');
        Route::get('conversations/{conversation}', [ConsultantChatController::class, 'show'])->name('conversations.show');
        Route::patch('conversations/{conversation}', [ConsultantChatController::class, 'update'])->name('conversations.update');
        Route::get('conversations/{conversation}/messages', [ConsultantChatController::class, 'messages'])->name('conversations.messages');
        Route::post('conversations/{conversation}/messages', [ConsultantChatController::class, 'sendMessage'])->name('conversations.messages.store');
        Route::post('conversations/{conversation}/read', [ConsultantChatController::class, 'read'])->name('conversations.read');
        Route::post('conversations/{conversation}/typing', [ConsultantChatController::class, 'typing'])->name('conversations.typing');
        Route::put('messages/{message}', [ConsultantChatController::class, 'updateMessage'])->name('messages.update');
        Route::delete('messages/{message}', [ConsultantChatController::class, 'destroyMessage'])->name('messages.destroy');
        Route::get('unread', [ConsultantChatController::class, 'unread'])->name('unread');
    });

    Route::get('/direct-chat', [ConsultantChatController::class, 'index'])
        ->middleware('consultant.feature:direct_chat')
        ->name('direct-chat');

    /*
    | Profile hub — the topnav's profile button leads straight here. Every
    | personal part (account, appearance studio, chat settings) renders
    | through the ONE profile route with ?tab=<section>; the browsing routes
    | that used to sit behind each section now just redirect into it, while
    | the save/POST endpoints stay independent (and keep their feature
    | gates). Gating moved from per-tab route middleware into the hub
    | controller via App\Support\SettingsTabs.
    */
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::redirect('/', '/consultant/settings/profile');

        Route::get('profile', [ConsultantProfileController::class, 'index'])
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

        // Legacy standalone tab URL → the profile hub's chat section.
        Route::redirect('chat', '/consultant/settings/profile?tab=chat');
        Route::post('chat', [ConsultantChatSettingsController::class, 'save'])
            ->middleware('consultant.feature:settings_chat')
            ->name('chat.save');

        /*
        | Appearance studio — schema-driven editor over the tenant's runtime
        | config layer (see config/studio.php), shown as the hub's appearance
        | section. "For everyone" is gated to the tenant admin inside the
        | controller; the flag only hides the section + write endpoints.
        */
        Route::redirect('appearance', '/consultant/settings/profile?tab=appearance');
        Route::prefix('appearance')->middleware('consultant.feature:theme_studio')->group(function () {
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

    /*
    | Deal renewal (تمدید و پرداخت) — dated periods per student; near the end
    | of each period the student chooses continue/withdraw in their portal and
    | submits their transfer receipt, while the tenant owner verifies it and
    | approving opens the next period. Gated by the tenant `deals` flag.
    */
    Route::prefix('deals')->name('deals.')->middleware('consultant.feature:deals')->group(function () {
        Route::get('/', [ConsultantDealController::class, 'index'])->name('index');
        Route::post('/', [ConsultantDealController::class, 'store'])->name('store');
        Route::post('payments/{payment}/review', [ConsultantDealController::class, 'reviewPayment'])->name('payments.review');
        Route::post('{deal}/remind', [ConsultantDealController::class, 'remind'])->name('remind');
    });

    /*
    | Notification center — the topnav bell. Panels land pre-filtered (due,
    | receipts, reminders); everything deal-related flows here instead of a
    | dead dropdown. Gated by the same `deals` flag.
    */
    Route::get('notifications', [ConsultantDealController::class, 'notifications'])
        ->middleware('consultant.feature:deals')
        ->name('notifications');

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

        Route::prefix('schedule/drafts')->name('schedule.drafts.')->middleware('consultant.feature:student_schedule')->group(function () {
            Route::get('/', [StudentScheduleController::class, 'drafts'])->name('index');
            Route::post('/', [StudentScheduleController::class, 'storeDraft'])->name('store');
            Route::put('/{draft}', [StudentScheduleController::class, 'updateDraft'])->name('update');
            Route::post('/{draft}/apply', [StudentScheduleController::class, 'applyDraft'])->name('apply');
        });

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
| Teacher Panel — dedicated sub-level of the tenant (users with the
| teacher role). Role-gated by EnsureTeacher; every section is also a
| tenant feature flag, so an academy can switch pieces off without code.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'teacher.role'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {
        Route::get('/dashboard', [TeacherDashboardController::class, 'index'])
            ->middleware('teacher.feature:teacher_panel')
            ->name('dashboard');

        Route::get('/students', [TeacherStudentController::class, 'index'])
            ->middleware('teacher.feature:teacher_panel')
            ->name('students');

        Route::get('/students/{student}', [TeacherStudentController::class, 'show'])
            ->middleware('teacher.feature:teacher_panel')
            ->name('students.show');

        Route::prefix('materials')->name('materials.')->middleware('teacher.feature:teacher_materials')->group(function () {
            Route::get('/', [TeacherMaterialController::class, 'index'])->name('index');
            Route::post('/', [TeacherMaterialController::class, 'store'])->name('store');
            Route::get('/{material}/download', [TeacherMaterialController::class, 'download'])->name('download');
            Route::delete('/{material}', [TeacherMaterialController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('assignments')->name('assignments.')->middleware('teacher.feature:teacher_assignments')->group(function () {
            Route::get('/', [TeacherAssignmentController::class, 'index'])->name('index');
            Route::get('/create', [TeacherAssignmentController::class, 'create'])->name('create');
            Route::post('/', [TeacherAssignmentController::class, 'store'])->name('store');
            Route::get('/{assignment}', [TeacherAssignmentController::class, 'show'])->name('show');
            Route::patch('/{assignment}', [TeacherAssignmentController::class, 'update'])->name('update');
            Route::delete('/{assignment}', [TeacherAssignmentController::class, 'destroy'])->name('destroy');
            Route::patch('/{assignment}/students/{student}', [TeacherAssignmentController::class, 'updateSubmission'])->name('submissions.update');
        });

        Route::prefix('schedule')->name('schedule.')->middleware('teacher.feature:teacher_schedule')->group(function () {
            Route::get('/', [TeacherScheduleController::class, 'index'])->name('index');
            Route::post('/', [TeacherScheduleController::class, 'store'])->name('store');
            Route::put('/{item}', [TeacherScheduleController::class, 'update'])->name('update');
            Route::delete('/{item}', [TeacherScheduleController::class, 'destroy'])->name('destroy');
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

    Route::get('report-card', [StudentReportCardPortalController::class, 'index'])
        ->middleware('student.feature:report_cards')
        ->name('report-card');

    /*
    | Lessons library, assignments and the class timetable — the teacher
    | panel's student-facing half, each gated by its own tenant flag.
    */
    Route::prefix('lessons')->name('lessons.')->middleware('student.feature:student_materials')->group(function () {
        Route::get('/', [StudentMaterialController::class, 'index'])->name('index');
        Route::get('/{material}/download', [StudentMaterialController::class, 'download'])->name('download');
    });

    Route::prefix('assignments')->name('assignments.')->middleware('student.feature:student_assignments')->group(function () {
        Route::get('/', [StudentAssignmentController::class, 'index'])->name('index');
        Route::get('/{assignment}', [StudentAssignmentController::class, 'show'])->name('show');
        Route::post('/{assignment}/submit', [StudentAssignmentController::class, 'submit'])->name('submit');
    });

    /*
    | Deal renewal — the student side of تمدید و پرداخت: the decision prompt
    | at every end of period, transfer-receipt submission and the persisted
    | notification feed. Gated by the same tenant `deals` flag as the panel.
    */
    Route::prefix('deals')->name('deals.')->middleware('student.feature:deals')->group(function () {
        Route::get('/', [StudentDealController::class, 'index'])->name('index');
        Route::post('{deal}/decision', [StudentDealController::class, 'decide'])->name('decide');
        Route::post('{deal}/payments', [StudentDealController::class, 'submitPayment'])->name('payments.store');
        Route::post('{deal}/pay-online', [StudentDealController::class, 'payOnline'])->name('payments.online');
    });

    /*
    | Notification center — the student portal's bell. The deal feed moved
    | here from the deals page; mark-read endpoints stay so the same page can
    | clear items inline. Gated by the `deals` flag that produces them.
    */
    Route::get('notifications', [StudentDealController::class, 'notifications'])
        ->middleware('student.feature:deals')
        ->name('notifications');
    Route::post('notifications/{notification}/read', [StudentDealController::class, 'readNotification'])
        ->middleware('student.feature:deals')
        ->name('notifications.read');
    Route::post('notifications/read-all', [StudentDealController::class, 'readAll'])
        ->middleware('student.feature:deals')
        ->name('notifications.readAll');

    Route::get('timetable', [StudentTimetableController::class, 'index'])
        ->middleware('student.feature:student_timetable')
        ->name('timetable');

    // Read-only review of the student's own finished attempts (the portal has
    // no exam-taking UI; sessions are run by the consultant).
    Route::get('exams/{assignment}/result', [StudentExamPortalController::class, 'result'])
        ->name('exams.result');

    /*
    | Student side of direct chat: read + reply in the threads a consultant
    | opened (direct or group). Creating threads or groups is consultant-only
    | — there is deliberately no POST endpoint here.
    */
    Route::prefix('direct-chat')->name('direct-chat.')->middleware('student.feature:student_chat')->group(function () {
        Route::get('/', [StudentChatController::class, 'index'])->name('page');
        Route::get('conversations', [StudentChatController::class, 'conversations'])->name('conversations');
        Route::get('conversations/{conversation}', [StudentChatController::class, 'show'])->name('conversations.show');
        Route::get('conversations/{conversation}/messages', [StudentChatController::class, 'messages'])->name('conversations.messages');
        Route::post('conversations/{conversation}/messages', [StudentChatController::class, 'sendMessage'])->name('conversations.messages.store');
        Route::post('conversations/{conversation}/read', [StudentChatController::class, 'read'])->name('conversations.read');
        Route::post('conversations/{conversation}/typing', [StudentChatController::class, 'typing'])->name('conversations.typing');
        Route::put('messages/{message}', [StudentChatController::class, 'updateMessage'])->name('messages.update');
        Route::delete('messages/{message}', [StudentChatController::class, 'destroyMessage'])->name('messages.destroy');
        Route::get('unread', [StudentChatController::class, 'unread'])->name('unread');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::redirect('/', '/student/settings/profile');

        Route::get('profile', [StudentProfileController::class, 'index'])->name('profile');
        Route::patch('profile', [StudentProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [StudentProfileController::class, 'updatePassword'])->name('profile.password');
        Route::put('profile/avatar', [StudentProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::delete('profile/avatar', [StudentProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
    });
});
