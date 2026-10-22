<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\BookLesson;
use App\Models\LessonFigure;
use App\Models\LessonReadingProgress;
use App\Models\LessonSourcePage;
use App\Models\Question;
use App\Models\User;
use App\Support\Lessons\LessonAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The student's lesson reader.
 *
 * This is the screen the whole feature exists for: a real lesson from the real
 * book, laid out to be readable (sticky TOC, floats, callouts), with a link
 * back to the page it was taken from so a student never has to wonder what the
 * book "actually" said.
 *
 * Access is deliberately the same rule as the material library — published,
 * and grade-matching or open to all — so the two lesson surfaces cannot drift.
 * Route names are `student.lesson-reader.*` because `student.lessons.*` already
 * belong to the material library (`routes/web.php`).
 */
class LessonReaderController extends Controller
{
    public function show(Request $request, BookLesson $lesson): View
    {
        $this->authorizeLesson($request, $lesson);

        $lesson->load(['blocks.figure', 'blocks' => fn ($q) => $q->orderBy('position')->orderBy('id')]);

        $student = $request->user('student');

        // Null on a staff preview: the reader then starts at the top and the
        // scroll meter posts to a route that declines to record anything.
        $progress = $student
            ? LessonReadingProgress::query()
                ->where('lesson_id', $lesson->id)
                ->where('student_id', $student->id)
                ->first()
            : null;

        $figures = LessonFigure::query()
            ->where('lesson_import_id', $lesson->lesson_import_id)
            ->orderBy('page_number')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        return view('student.lesson-reader', [
            'lesson' => $lesson,
            'blocks' => $lesson->blocks,
            'figures' => $figures,
            'sections' => $this->sections($lesson),
            'questions' => $this->questionsFor($lesson),
            'progressIndex' => (int) ($progress->max_section_index ?? 0),
            'previewing' => $student === null,
        ]);
    }

    /**
     * Grade ONE question of this lesson, in place.
     *
     * The demo this feature came from shows the answer inline; a real reader
     * must not, so the correct option is never rendered into the HTML — the
     * client posts the answer it picked and the server says whether it was
     * right, plus the explanation the reviewer approved.
     *
     * Ownership is proven through `lesson_ai_drafts`: a released question is
     * linked back by `released_question_id`, which is the only exact link
     * between a shared-bank row and the lesson whose text produced it
     * (`chapter_label` is a coarse grouping, not an identity).
     */
    public function answer(Request $request, BookLesson $lesson): JsonResponse
    {
        $this->authorizeLesson($request, $lesson);

        $data = $request->validate([
            'question' => ['required', 'integer', 'min:1'],
            'answer' => ['required', 'integer', 'min:1'],
        ]);

        $questionId = (int) $data['question'];

        // Ownership is the question's OWN link to the lesson. It used to be
        // proven through `lesson_ai_drafts.released_question_id`, a single id
        // per lesson -- so a lesson with three questions could grade only the
        // first, and answering any other one was a 404 it could not explain.
        $question = Question::query()
            ->where('lesson_id', $lesson->id)
            ->findOrFail($questionId);

        $answers = Answer::query()
            ->where('question_id', $questionId)
            ->orderBy('id')
            ->get();

        $chosen = $answers->firstWhere('id', (int) $data['answer']);

        abort_if($chosen === null, 422);

        return response()->json([
            'correct' => (bool) $chosen->is_correct,
            'correct_answer' => $answers->firstWhere('is_correct', true)?->id,
            'explanation' => (string) $question->solution_text,
        ]);
    }

    /**
     * The AI questions a reviewer released for THIS lesson.
     *
     * Empty on a draft or on a lesson nobody generated questions for — the view
     * then simply omits the whole quiz section, which is the honest state.
     *
     * @return Collection<int, Question>
     */
    private function questionsFor(BookLesson $lesson)
    {
        return Question::query()
            ->with(['answers' => fn ($query) => $query->orderBy('id')])
            ->where('lesson_id', $lesson->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * One source page as a PNG, so «صفحهٔ ۱۲ کتاب» opens the real page.
     *
     * Scoped exactly like the reader: a student can only reach pages of a
     * lesson they are allowed to read, which is also what keeps another
     * tenant's book pages unreachable.
     */
    public function page(Request $request, BookLesson $lesson, int $pageNumber): BinaryFileResponse
    {
        $this->authorizeLesson($request, $lesson);

        $source = LessonSourcePage::query()
            ->where('lesson_import_id', $lesson->lesson_import_id)
            ->where('page_number', $pageNumber)
            ->firstOrFail();

        $absolute = public_path('tenants/'.$lesson->tenant->slug.'/'.ltrim($source->image_path, '/'));

        abort_unless(is_file($absolute), 404);

        return response()->file($absolute, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Record how far the student has read. Silent and idempotent: the client
     * posts the highest section index it has scrolled past, and we only ever
     * move the marker forward.
     */
    public function progress(Request $request, BookLesson $lesson): JsonResponse
    {
        $this->authorizeLesson($request, $lesson);

        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
        ]);

        $student = $request->user('student');

        /*
         | A staff preview has no student row to attribute reading to, and must
         | not invent one: progress is the student's, and a consultant's scroll
         | would otherwise show up as the student having read the lesson.
         */
        if (! $student) {
            return response()->json(['index' => (int) $data['index'], 'completed' => false]);
        }

        $progress = LessonReadingProgress::query()->firstOrNew([
            'tenant_id' => $lesson->tenant_id,
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
        ]);

        $incoming = (int) $data['index'];

        if ($incoming > (int) $progress->max_section_index) {
            $progress->max_section_index = $incoming;
            $progress->completed_at = $incoming >= max(0, count($this->sections($lesson)) - 1)
                ? ($progress->completed_at ?? now())
                : $progress->completed_at;
            $progress->save();
        }

        return response()->json([
            'index' => (int) $progress->max_section_index,
            'completed' => $progress->completed_at !== null,
        ]);
    }

    /**
     * The headings, in order — the reader's table of contents and the unit its
     * scroll-spy counts in. A lesson with no heading still gets one section so
     * the meter has something to measure.
     *
     * @return list<array{index: int, text: string, anchor: string}>
     */
    private function sections(BookLesson $lesson): array
    {
        $sections = [];
        $index = 0;

        foreach ($lesson->blocks as $block) {
            if ($block->type !== 'heading') {
                continue;
            }

            $sections[] = [
                'index' => $index,
                'text' => (string) ($block->data['text'] ?? ''),
                'anchor' => (string) ($block->anchor ?: 'section-'.$index),
            ];

            $index++;
        }

        if ($sections === []) {
            $sections[] = ['index' => 0, 'text' => $lesson->lesson_title, 'anchor' => 'section-0'];
        }

        return $sections;
    }

    private function authorizeLesson(Request $request, BookLesson $lesson): void
    {
        $student = $request->user('student');

        if ($student) {
            abort_unless(
                (int) $lesson->tenant_id === (int) $student->tenant_id
                && $lesson->isPublished()
                && ($lesson->grade === null || $lesson->grade === $student->grade),
                404
            );

            return;
        }

        /*
         | Staff preview. The staff reader link is the hint that a consultant
         | must be able to check a lesson before students see it, and staff have
         | no `student` session — so a preview from the panel would 404 on every
         | lesson. Only this tenant's own staff may preview, and a DRAFT is
         | previewable (that is the whole point: review before publishing).
         |
         | A platform admin is the operator who ran the extraction in /ops, so
         | they may preview too — scoped to the tenant host they are standing on.
         */
        $user = $request->user();
        $isOperator = $user instanceof User && $user->canRunOps();

        abort_unless(
            ($isOperator || LessonAccess::staffCanAuthor())
            && (int) $lesson->tenant_id === (int) (tenant()?->id),
            404
        );
    }
}
