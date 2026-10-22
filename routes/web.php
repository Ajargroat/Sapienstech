<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\StudentLoginController;
use App\Http\Controllers\Consultant\BlogController as ConsultantBlogController;
use App\Http\Controllers\Consultant\Bulk\BulkExamController;
use App\Http\Controllers\Consultant\Bulk\BulkHistoryController;
use App\Http\Controllers\Consultant\Bulk\BulkScheduleController;
use App\Http\Controllers\Consultant\ChatController as ConsultantChatController;
use App\Http\Controllers\Consultant\ConsultantDashboardController;
use App\Http\Controllers\Consultant\ConsultantFeatureController;
use App\Http\Controllers\Consultant\ConsultationRequestController as ConsultantConsultationRequestController;
use App\Http\Controllers\Consultant\DealController as ConsultantDealController;
use App\Http\Controllers\Consultant\JoinRequestController as ConsultantJoinRequestController;
use App\Http\Controllers\Consultant\ScheduleAssistantController;
use App\Http\Controllers\Consultant\Settings\ChatSettingsController as ConsultantChatSettingsController;
use App\Http\Controllers\Consultant\Settings\ProfileController as ConsultantProfileController;
use App\Http\Controllers\Consultant\StudentExamController;
use App\Http\Controllers\Consultant\StudentFeatureController;
use App\Http\Controllers\Consultant\StudentReportCardController;
use App\Http\Controllers\Consultant\StudentScheduleController;
use App\Http\Controllers\Consultant\StudentScheduleNoteController;
use App\Http\Controllers\Public\BlogController as PublicBlogController;
use App\Http\Controllers\Public\ConsultationRequestController;
use App\Http\Controllers\Public\JoinRequestController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Student\ChatController as StudentChatController;
use App\Http\Controllers\Student\DealController as StudentDealController;
use App\Http\Controllers\Student\LessonReaderController;
use App\Http\Controllers\Student\Settings\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\StudentAssignmentController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\StudentExamController as StudentExamPortalController;
use App\Http\Controllers\Student\StudentMaterialController;
use App\Http\Controllers\Student\StudentReportCardController as StudentReportCardPortalController;
use App\Http\Controllers\Student\StudentSchedulePortalController;
use App\Http\Controllers\Student\StudentTimetableController;
use App\Http\Controllers\Studio\StudioController;
use App\Http\Controllers\Studio\StudioDraftController;
use App\Http\Controllers\Teacher\TeacherAssignmentController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
use App\Http\Controllers\Teacher\TeacherMaterialController;
use App\Http\Controllers\Teacher\TeacherScheduleController;
use App\Http\Controllers\Teacher\TeacherStudentController;
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
 * Bespoke landing draft preview. Staff-only (`auth` is the web guard, so
 * students and guests never reach it): renders the tenant's surface with
 * force:true, i.e. the `enabled` flag is bypassed but the slug and file
 * checks still apply. Same `*.landing` composer as the published page, plus
 * X-Robots-Tag noindex from the controller. Never linked publicly.
 */
Route::get('/landing/preview', [PageController::class, 'preview'])
    ->middleware('auth')
    ->name('landing.preview');

/*
| Join request («درخواست عضویت») — the landing page's join section posts here.
| Public by necessity (the visitor has no account yet), so it is throttled and
| the tenant is taken from the host, never from the form. The row it creates is
| a *pending* request, not a student: see App\Support\StudentEnrollment.
*/
Route::post('/join', [JoinRequestController::class, 'store'])
    ->middleware('throttle:join-request')
    ->name('join.store');

/*
| Consultation request - a one-way public submission: the visitor asks to be
| contacted back; there is no payment and nothing becomes a roster row.
| Public like /join, so it is throttled and the tenant is taken from the
| host, never from the form. The staff email rides the queue. Wave 2b adds
| the flag row and the shell - until `features.consultation_requests` is
| configured, site() answers false and this route 404s.
*/
Route::post('/consultation', [ConsultationRequestController::class, 'store'])
    ->middleware('throttle:consultation-request')
    ->name('consultation.store');

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
| Operations console (/ops) — ours, not a tenant's.
|--------------------------------------------------------------------------
|
| The PDF extraction pipeline lives here and nowhere else. It carries its own
| platform-admin gate (`ops.access`), its own `ops.` route names and no tenant
| feature flag, so no panel's role matrix or shell decides whether our own
| machinery works. See routes/ops.php for why extraction runs inline.
*/
require __DIR__.'/ops.php';

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
        | Appearance studio — moved to the standalone /studio page (the
        | `studio.*` group at the bottom of the consultant area). Legacy
        | appearances of the old URLs redirect there: GET with a 302 (the
        | profile-hub tab is gone), POST endpoints with a 308 so method and
        | body survive for anything still pointed at them.
        */
        Route::redirect('appearance', '/studio');
        Route::prefix('appearance')->group(function () {
            Route::post('/', fn () => redirect()->route('studio.save', [], 308));
            Route::post('reset', fn () => redirect()->route('studio.reset', [], 308));
            Route::post('reset-all', fn () => redirect()->route('studio.reset.all', [], 308));
            Route::post('preview/exit', fn () => redirect()->route('studio.preview.exit', [], 308));
            Route::post('live', fn () => redirect()->route('studio.live', [], 308));
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

    /*
    | Join requests («درخواست عضویت») — the queue of visitors who imported their
    | data on the public site and declared a payment. Approving one creates the
    | student. The admin can also add a student by hand here, for a person
    | assigned outside the request flow (no visitor, no declared payment);
    | both write through the same service. Gated by the tenant
    | `student_join_requests` flag, which is the roster-management switch.
    */
    Route::prefix('requests')->name('requests.')->middleware('consultant.feature:student_join_requests')->group(function () {
        Route::get('/', [ConsultantJoinRequestController::class, 'index'])->name('index');

        // The literal `create` path must precede `{joinRequest}`, or the model
        // binding would swallow it as a request id.
        Route::get('create', [ConsultantJoinRequestController::class, 'create'])->name('create');
        Route::post('create', [ConsultantJoinRequestController::class, 'store'])->name('store');

        Route::get('{joinRequest}', [ConsultantJoinRequestController::class, 'show'])->name('show');
        Route::post('{joinRequest}/approve', [ConsultantJoinRequestController::class, 'approve'])->name('approve');
        Route::post('{joinRequest}/reject', [ConsultantJoinRequestController::class, 'reject'])->name('reject');
    });

    /*
    | Consultation requests («درخواست مشاوره») — the queue of visitors who
    | asked to be contacted back from the tenant's public consultation form.
    | A pure status decision: approving creates nothing, rejecting deletes
    | nothing — the row is the record (App\Support\ConsultationRequests).
    | Gated by the tenant `consultation_requests` flag.
    */
    Route::prefix('consultations')->name('consultations.')->middleware('consultant.feature:consultation_requests')->group(function (): void {
        Route::get('/', [ConsultantConsultationRequestController::class, 'index'])->name('index');
        Route::get('{consultationRequest}', [ConsultantConsultationRequestController::class, 'show'])->name('show');
        Route::post('{consultationRequest}/approve', [ConsultantConsultationRequestController::class, 'approve'])->name('approve');
        Route::post('{consultationRequest}/reject', [ConsultantConsultationRequestController::class, 'reject'])->name('reject');
    });

    Route::get('/permissions', [ConsultantFeatureController::class, 'show'])->defaults('feature', 'permissions')->middleware('consultant.feature:book_access')->name('permissions');
    Route::get('/questions', [ConsultantFeatureController::class, 'show'])->defaults('feature', 'questions')->middleware('consultant.feature:question_management')->name('questions');
    Route::get('/quizzes', [ConsultantFeatureController::class, 'show'])->defaults('feature', 'quizzes')->middleware('consultant.feature:quiz_management')->name('quizzes');

    /*
    | The lesson library's staff surface (import + block editor + AI drafts +
    | question review). Mounted a SECOND time under /teacher/lessons from the
    | identical require below — same controllers, same LessonAccess.
    |
    | `require` inside this closure is evaluated WHILE the group is active, so
    | the `consultant.` name root and `auth` are inherited. A require at the
    | file's top level does NOT inherit anything: it would register the same
    | routes ungated at /lessons (measured: `lessons.index` with only `web`),
    | which is why this file must be required ONLY from here and from the
    | teacher group. The mount adds ONLY the panel name root; `lessons.php`
    | owns its own `lessons` URI prefix and name segment (the previous version
    | declared both ends, producing `/consultant/lessons/lessons` and
    | `consultant.lessons.lessons.*` — measured on `route:list`).
    */
    Route::group([], function () {
        require __DIR__.'/lessons.php';
    });

    Route::prefix('students/{student}')->name('student.')->group(function () {

        Route::get('/', [StudentFeatureController::class, 'profile'])
            ->middleware('consultant.feature:student_profile')
            ->name('profile');

        /*
        | Remove the student from the roster, from their own profile page.
        | Soft: the row and its history survive; the person leaves every list
        | and can no longer log in. Owner-only, enforced in the controller.
        */
        Route::delete('/', [ConsultantJoinRequestController::class, 'removeStudent'])
            ->middleware('consultant.feature:student_join_requests')
            ->name('remove');

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

        // Order is owned by the preview: the chosen questions, as arranged.
        Route::get('/exams/preview', [StudentExamController::class, 'preview'])
            ->middleware('consultant.feature:student_exams')
            ->name('exams.preview');

        // Read-only review of the exam — the consultant does not sit it, so
        // there is no consultant attempt or submit route.
        Route::get('/exams/{assignment}/run', [StudentExamController::class, 'run'])
            ->middleware('consultant.feature:student_exams')
            ->name('exams.run');

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
            Route::post('/{item}/comments', [StudentScheduleNoteController::class, 'storeComment'])->name('comments.store');
        });

        /*
        | Weekly notes («توضیحات هفته») and the block-dialog's title suggestions.
        | Both are consultant-authored, student-readable, and gated by the same
        | student_schedule feature as the planner itself.
        */
        Route::prefix('schedule/notes')->name('schedule.notes.')->middleware('consultant.feature:student_schedule')->group(function () {
            Route::get('/', [StudentScheduleNoteController::class, 'notes'])->name('index');
            Route::put('/', [StudentScheduleNoteController::class, 'updateNotes'])->name('update');
        });

        Route::get('/schedule/suggestions', [StudentScheduleNoteController::class, 'suggestions'])
            ->middleware('consultant.feature:student_schedule')
            ->name('schedule.suggestions');

        /*
        | The weekly-planner AI assistant.
        |
        | Gated by the SAME `student_schedule` feature as the planner, so a
        | tenant with the planner switched off cannot reach the assistant
        | either -- there would be nothing for it to talk about. The tenant-level
        | `ai.enabled` switch is checked in the controller rather than here,
        | because it is a per-tenant behaviour knob (site('ai.enabled')) and not a
        | route-existence question; the panel renders its disabled state instead
        | of the page 404ing.
        |
        | Note the deliberate absence of a write route: suggestions are applied
        | through `schedule.items.*` above. The assistant has no mutation path of
        | its own, which is what keeps one validation path for every block.
        */
        Route::prefix('schedule/assistant')->name('schedule.assistant.')->middleware('consultant.feature:student_schedule')->group(function () {
            Route::get('/', [ScheduleAssistantController::class, 'show'])->name('show');
            Route::post('/messages', [ScheduleAssistantController::class, 'send'])->name('send');
            Route::delete('/', [ScheduleAssistantController::class, 'clear'])->name('clear');
            Route::post('/health', [ScheduleAssistantController::class, 'healthCheck'])->name('health');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Theme Studio (standalone) — the appearance editor as its own page.
|
| Same host as the tenant site on purpose: IdentifyTenant resolves the
| tenant from the Host header, so tenant resolution, session cookies,
| uploads and the same-origin preview iframe all keep working unchanged.
| The dashboard opens it in a new tab; the `studio.access` gate carries
| the membership/owner rules, and `consultant.feature:theme_studio`
| remains the tenant-level feature switch. The page renders through the
| platform-only layouts.studio shell — no tenant theme partials.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'consultant.feature:theme_studio', 'studio.access'])
    ->prefix('studio')
    ->name('studio.')
    ->group(function () {
        Route::get('/', [StudioController::class, 'index'])->name('index');
        Route::post('/', [StudioController::class, 'save'])->name('save');
        Route::post('reset', [StudioController::class, 'resetKey'])->name('reset');
        Route::post('reset-all', [StudioController::class, 'resetAll'])->name('reset.all');
        Route::post('preview/exit', [StudioController::class, 'exitPreview'])->name('preview.exit');
        Route::post('live', [StudioController::class, 'live'])->name('live');

        // The single named draft (one per tenant+user) — JSON carriers only;
        // a restore re-submits the payload to studio.save as scope=preview.
        Route::get('draft', [StudioDraftController::class, 'show'])->name('draft.show');
        Route::post('draft', [StudioDraftController::class, 'store'])->name('draft.store');
        Route::put('draft', [StudioDraftController::class, 'update'])->name('draft.update');
        Route::delete('draft', [StudioDraftController::class, 'destroy'])->name('draft.destroy');
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

        /*
        | The lesson library, second mount. A school teacher must work inside
        | the teacher shell, not the whole-tenant consultant one — and a
        | consultancy's staff must not be locked out by EnsureTeacher, which is
        | why the surface exists under both prefixes with ONE controller set.
        | Required inside the closure (no extra prefix/name root) so `teacher.`
        | + `auth` + `teacher.role` are inherited — never at the top level of
        | this file. lessons.php owns the `lessons` URI prefix and name segment.
        */
        Route::group([], function () {
            require __DIR__.'/lessons.php';
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

    /*
    | The student's own half of the weekly planner: the consultant's notes for
    | the week, marking a block done, and commenting on a block. Gated by the
    | same tenant flag that publishes the planner to the consultant, so a tenant
    | without student_schedule cannot reach the JSON either.
    |
    | {item} is bound by id and re-checked against the authenticated student in
    | the controller -> a guessed id answers 404, never a redirect.
    */
    Route::prefix('schedule')->name('schedule.')->middleware('student.feature:student_schedule')->group(function () {
        Route::get('notes', [StudentSchedulePortalController::class, 'notes'])->name('notes');
        Route::post('items/{item}/completion', [StudentSchedulePortalController::class, 'toggleCompletion'])->name('items.completion');
        Route::get('items/{item}/comments', [StudentSchedulePortalController::class, 'comments'])->name('items.comments');
        Route::post('items/{item}/comments', [StudentSchedulePortalController::class, 'storeComment'])->name('items.comments.store');
    });

    /*
    | Exams the student sits themselves. The card on the dashboard opens the
    | runner once the exam is inside its window; the countdown and the grading
    | are both the server's, and the finished attempt is reviewed read-only.
    | Ungated like the result route below (no exam exists to sit when the
    | tenant's student_exams flag is off).
    */
    Route::get('exams/{assignment}', [StudentExamPortalController::class, 'show'])
        ->name('exams.show');

    Route::post('exams/{assignment}/attempt', [StudentExamPortalController::class, 'storeAttempt'])
        ->name('exams.attempt');

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

/*
|--------------------------------------------------------------------------
| Lesson reader — the book-lesson screen, for students AND staff preview.
|--------------------------------------------------------------------------
|
| Deliberately NOT inside the `auth:student` group above: that guard sends a
| consultant to /student/login, so a staff preview would never reach the
| controller (the reader is the one screen both audiences share). Its own
| group, `auth:student` OR a tenant staff session, and the controller decides
| which rule applies — published+grade for a student, own-tenant for staff.
|
| Route names are `student.lesson-reader.*` and NOT `student.lessons.*`: that
| prefix is the material library, and the later registration would silently
| win the name.
|
*/
Route::middleware(['lesson-reader.access'])->prefix('student/lesson-reader')->name('student.lesson-reader.')->group(function () {
    Route::get('/{lesson}', [LessonReaderController::class, 'show'])->name('show');
    // `page` is the reserved paginator name; the parameter is `pageNumber`.
    Route::get('/{lesson}/pages/{pageNumber}', [LessonReaderController::class, 'page'])
        ->whereNumber('pageNumber')
        ->name('page');
    Route::post('/{lesson}/progress', [LessonReaderController::class, 'progress'])->name('progress');
    // Grades ONE released question in place; the answer key never ships in the
    // HTML (the demo showed it because the demo was static).
    Route::post('/{lesson}/answer', [LessonReaderController::class, 'answer'])->name('answer');
});
