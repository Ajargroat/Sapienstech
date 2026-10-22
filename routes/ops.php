<?php

/*
| The OPERATIONS console — our own surface, outside every tenant panel.
|
| Why it exists: the PDF pipeline used to be mounted under /consultant/lessons
| and /teacher/lessons, which meant "does the extraction work?" depended on a
| tenant's feature flag, a staff role, and a queue worker somebody had started.
| It is our machinery: we upload the book, we extract it, and only the finished
| lesson reaches a tenant.
|
| Extraction runs IN THE REQUEST here (no queue, no polling, no daemon). The
| operator asks for it and gets the outcome on the page they are looking at.
|
| Everything is platform-admin only (`ops.access`); every URL is namespaced
| `ops.` so no tenant view can accidentally resolve one.
*/

use App\Http\Controllers\Ops\LessonOpsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'ops.access'])->prefix('ops')->name('ops.')->group(function () {
    Route::get('/', fn () => redirect()->route('ops.lessons.index'))->name('home');

    Route::get('lessons', [LessonOpsController::class, 'index'])->name('lessons.index');
    Route::post('lessons', [LessonOpsController::class, 'store'])->name('lessons.store');
    Route::get('lessons/{import}', [LessonOpsController::class, 'show'])->name('lessons.show');
    Route::post('lessons/{import}/extract', [LessonOpsController::class, 'extract'])->name('lessons.extract');
    Route::post('lessons/{import}/build', [LessonOpsController::class, 'build'])->name('lessons.build');
    Route::get('lessons/{lesson}/manage', [LessonOpsController::class, 'lesson'])->name('lessons.lesson');
    Route::post('lessons/{lesson}/publish', [LessonOpsController::class, 'publish'])->name('lessons.publish');
    Route::post('lessons/{lesson}/unpublish', [LessonOpsController::class, 'unpublish'])->name('lessons.unpublish');
    Route::post('lessons/{lesson}/ask', [LessonOpsController::class, 'ask'])->name('lessons.ask');
    Route::post('lessons/{lesson}/release', [LessonOpsController::class, 'release'])->name('lessons.release');
    Route::get('lessons/{import}/pages/{page}', [LessonOpsController::class, 'page'])
        ->whereNumber('page')
        ->name('lessons.page');
});
