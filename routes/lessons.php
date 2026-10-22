<?php

/*
| The staff lesson surface - mounted TWICE from routes/web.php.
|
| The user's decision (PREFERENCE 7.14): /consultant/lessons/* AND
| /teacher/lessons/*, one controller set, one access class (LessonAccess), and
| no view may hardcode either prefix (they all call lessons_route()).
|
| routes/web.php requires this file inside each panel's group, with `lessons.`
| as the name prefix and that panel's `*.feature:lesson_library` middleware, so
| the two mounts can never drift apart.
*/

use App\Http\Controllers\Lessons\LessonAiController;
use App\Http\Controllers\Lessons\LessonEditorController;
use App\Http\Controllers\Lessons\LessonImportController;
use App\Http\Controllers\Lessons\LessonQuestionController;
use Illuminate\Support\Facades\Route;

// The URI prefix + name segment live HERE (mount sites in routes/web.php add
// only the panel's `*.` name root), so the two mounts can never drift apart.
Route::middleware('lessons.feature:lesson_library')->prefix('lessons')->name('lessons.')->group(function () {

    // --- the staff landing: authored lessons + what came out of the pipeline -
    //
    // Uploading and extracting a book is NOT a tenant action any more. It lives
    // in the platform-admin operations console (/ops/lessons) because it is our
    // machinery: no consultant flag should decide whether it runs, and a failed
    // extraction must never surface to a tenant as a page that refreshes itself
    // forever. What remains here is the tenant-facing half — the lessons that
    // came out of it — plus the editor below.
    Route::get('/', [LessonImportController::class, 'index'])->name('index');

    // --- one rendered source page (the editor's left pane + the import report)
    //
    // The controller method and both views that call lessons_route('import.page')
    // are older than this route: without it, every editor render for a lesson
    // with source pages threw RouteNotFoundException while generating the <img>
    // src (PLAN react-02 S1).
    Route::get('import/{import}/page/{page}', [LessonImportController::class, 'page'])
        ->whereNumber('page')
        ->name('import.page');

    // --- the block editor --------------------------------------------------
    Route::get('editor/{lesson}', [LessonEditorController::class, 'edit'])->name('editor.edit');
    Route::post('editor/{lesson}/blocks', [LessonEditorController::class, 'storeBlocks'])->name('editor.blocks');
    Route::get('editor/{lesson}/figures', [LessonEditorController::class, 'figures'])->name('editor.figures');
    Route::post('editor/{lesson}/publish', [LessonEditorController::class, 'publish'])->name('editor.publish');
    Route::post('editor/{lesson}/revise', [LessonEditorController::class, 'revise'])->name('editor.revise');

    // --- AI drafts (enqueue only; the flags 404 them when off) -------------
    Route::post('editor/{lesson}/ai/blocks', [LessonAiController::class, 'blocks'])->name('ai.blocks');
    Route::get('editor/{lesson}/ai/{draft}', [LessonAiController::class, 'draft'])->name('ai.draft');
    Route::post('editor/{lesson}/ai/questions', [LessonAiController::class, 'questions'])->name('ai.questions');

    // --- question review + release ----------------------------------------
    Route::get('editor/{lesson}/questions', [LessonQuestionController::class, 'review'])->name('questions.review');
    Route::post('editor/{lesson}/questions/{draft}/approve', [LessonQuestionController::class, 'approve'])->name('questions.approve');
    Route::post('editor/{lesson}/questions/{draft}/reject', [LessonQuestionController::class, 'reject'])->name('questions.reject');
    Route::post('editor/{lesson}/questions/{draft}/release', [LessonQuestionController::class, 'release'])->name('questions.release');
    Route::post('editor/{lesson}/questions/{draft}/release-all', [LessonQuestionController::class, 'releaseAll'])->name('questions.release-all');
});
