<?php

namespace App\Http\Controllers\Lessons;

use App\Http\Controllers\Controller;
use App\Models\BookLesson;
use App\Models\LessonAiDraft;
use App\Support\Lessons\LessonAccess;
use App\Support\Lessons\LessonDrafter;
use App\Support\Lessons\LessonQuestionRelease;
use App\Support\Lessons\StatusLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * The human gate in front of the question bank.
 *
 * A draft is produced by the queue, then a person reads each question and
 * either approves or rejects the WHOLE draft (the unit of review is the draft,
 * because the questions were written together and read together), and only
 * then may individual approved questions be released into the shared bank —
 * one at a time, so a reviewer can keep the good ones.
 *
 * States: ready → approved | rejected; approved → released. Anything else is
 * a 409, not a silent no-op.
 */
class LessonQuestionController extends Controller
{
    public function review(BookLesson $lesson): View
    {
        LessonAccess::authorizeStaff($lesson);

        $drafts = $lesson->drafts()->where('kind', LessonAiDraft::KIND_QUESTIONS)->get();

        return view('lessons.questions', [
            'lesson' => $lesson,
            'drafts' => $drafts,
            'draftLabels' => StatusLabels::draft(),
            'page' => 'lessons',
            'aiEnabled' => LessonDrafter::questionsEnabled(),
            'releaseEnabled' => LessonDrafter::releaseEnabled(),
            'pendingDraft' => $drafts->firstWhere('status', 'queued') ?? $drafts->firstWhere('status', 'running'),
            'maxQuestions' => (int) site('lessons.ai_questions_max', 12),
            'defaultQuestions' => (int) site('lessons.ai_questions_default', 5),
        ]);
    }

    public function approve(BookLesson $lesson, LessonAiDraft $draft): RedirectResponse
    {
        LessonAccess::authorizeStaff($lesson);

        $this->assertBelongs($lesson, $draft);
        abort_unless($draft->status === 'ready', 409, 'Only a ready draft can be approved.');

        $draft->forceFill(['status' => 'approved'])->save();

        return back()->with('success', 'پیش‌نویس تأیید شد؛ اکنون می‌توانید سؤال‌ها را وارد بانک کنید.');
    }

    public function reject(BookLesson $lesson, LessonAiDraft $draft): RedirectResponse
    {
        LessonAccess::authorizeStaff($lesson);

        $this->assertBelongs($lesson, $draft);
        abort_unless(in_array($draft->status, ['ready', 'approved'], true), 409);

        // Rejected drafts are KEPT: they are the audit trail of what the model
        // proposed and a human refused.
        $draft->forceFill(['status' => 'rejected'])->save();

        return back()->with('success', 'پیش‌نویس رد شد.');
    }

    /** Write one approved question into the shared bank. */
    public function release(Request $request, BookLesson $lesson, LessonAiDraft $draft, LessonQuestionRelease $release): RedirectResponse
    {
        LessonAccess::authorizeStaff($lesson);

        $this->assertBelongs($lesson, $draft);

        abort_unless(LessonDrafter::releaseEnabled(), 404);
        abort_unless($draft->status === 'approved', 409, 'Only an approved draft can be released.');
        abort_if($draft->released_question_id !== null, 409, 'This draft was already released.');

        $data = $request->validate(['index' => ['required', 'integer', 'min:0']]);

        try {
            $release->release($draft, $lesson, (int) $data['index']);
        } catch (RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return back()->with('success', 'سؤال به بانک مشترک سؤال اضافه شد.');
    }

    /**
     * Approve every released-eligible draft AND release all of its questions.
     * Kept only for the "approve all" affordance in the review view — it uses
     * the same two-step state machine, never a shortcut through it.
     */
    public function releaseAll(BookLesson $lesson, LessonAiDraft $draft, LessonQuestionRelease $release): RedirectResponse
    {
        LessonAccess::authorizeStaff($lesson);

        $this->assertBelongs($lesson, $draft);

        abort_unless(LessonDrafter::releaseEnabled(), 404);
        abort_unless($draft->status === 'approved', 409, 'Only an approved draft can be released.');

        $count = count($draft->questions());
        $released = 0;

        for ($index = 0; $index < $count; $index++) {
            try {
                DB::transaction(fn () => $release->release($draft, $lesson, $index));
                $released++;
            } catch (RuntimeException) {
                break; // already released on the first pass; stop cleanly.
            }
        }

        return back()->with('success', "{$released} سؤال وارد بانک شد.");
    }

    private function assertBelongs(BookLesson $lesson, LessonAiDraft $draft): void
    {
        abort_unless(
            (int) $draft->lesson_id === (int) $lesson->id
            && (int) $draft->tenant_id === (int) $lesson->tenant_id,
            404
        );

        abort_unless($draft->kind === LessonAiDraft::KIND_QUESTIONS, 404);
    }
}
