<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Jobs\ExtractLessonPdf;
use App\Jobs\GenerateLessonBlocks;
use App\Models\BookLesson;
use App\Models\LessonAiDraft;
use App\Models\LessonImport;
use App\Models\Question;
use App\Support\LessonBuilder;
use App\Support\LessonImportStorage;
use App\Support\LessonPdf\PdfExtractor;
use App\Support\Lessons\LessonDrafter;
use App\Support\Lessons\LessonQuestionRelease;
use App\Support\Lessons\StatusLabels;
use App\Support\TenantUploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * The book pipeline, run by us.
 *
 * This is the ONE place a PDF is uploaded and extracted. It is deliberately
 * NOT a tenant feature: no consultant flag turns it on, no teacher role opens
 * it, and it is unreachable from every tenant shell.
 *
 * The load-bearing decision here is that the heavy steps run INLINE (no queue,
 * no polling, no daemon). The queue version existed and produced the worst
 * possible failure: a job that could not even be constructed left the import
 * row on `queued` forever while the page promised it would refresh itself. An
 * operator pressing a button and waiting is honest; a background worker nobody
 * started is not.
 *
 * The second load-bearing decision is that this console can FINISH the chain.
 * `build` alone produced a draft, drafts are invisible to students, and the
 * publish button existed only in the tenant editor — so the whole console
 * dead-ended in content no student could ever open, and the AI questions never
 * reached a lesson page. `publish` and `release` are here because "a student
 * can read it" is the only meaningful definition of done.
 */
class LessonOpsController extends Controller
{
    public function index(): View
    {
        $imports = LessonImport::query()
            ->withCount(['sourcePages', 'figures', 'lessons'])
            ->orderByDesc('id')
            ->get();

        $lessons = BookLesson::query()
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return view('ops.lessons', [
            'imports' => $imports,
            'lessons' => $lessons,
            'statusLabels' => StatusLabels::import(),
            'lessonLabels' => StatusLabels::lesson(),
            'maxPages' => (int) site('lessons.max_pages', 400),
            'maxKb' => max(1024, (int) site('academics.materials.max_kb', 20480)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $maxKb = max(1024, (int) site('academics.materials.max_kb', 20480));

        $data = $request->validate([
            'book_title' => ['required', 'string', 'max:191'],
            'grade' => ['nullable', 'string', 'max:32'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.$maxKb],
        ]);

        $import = LessonImport::create([
            'book_title' => $data['book_title'],
            'grade' => $data['grade'] ?? null,
            'pdf_path' => TenantUploads::store($request->file('file'), 'lessons/pdf'),
            'status' => 'queued',
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->runNow($import);

        return redirect()
            ->to(route('ops.lessons.show', $import))
            ->with('success', 'استخراج انجام شد: '.($import->fresh()->status === 'ready' ? 'متن آماده است.' : 'وضعیت را ببینید.'));
    }

    public function show(LessonImport $import): View
    {
        $import->load(['sourcePages', 'figures', 'lessons']);

        return view('ops.import', [
            'import' => $import,
            'statusLabels' => StatusLabels::import(),
            'structureLabels' => StatusLabels::structure(),
            'lessonLabels' => StatusLabels::lesson(),
            'figures' => $import->figures->groupBy('page_number'),
            'pages' => $import->sourcePages->groupBy('page_number'),
        ]);
    }

    /** Re-run extraction for an import that failed or came out unusable. */
    public function extract(LessonImport $import): RedirectResponse
    {
        $this->runNow($import);

        return back()->with('success', 'استخراج دوباره اجرا شد.');
    }

    /** Turn a page range into a draft lesson full of text blocks. */
    public function build(Request $request, LessonImport $import): RedirectResponse
    {
        abort_unless($import->status === 'ready', 422, 'این کتاب متن قابل استفاده ندارد.');

        $data = $request->validate([
            'lesson_title' => ['required', 'string', 'max:191'],
            'chapter_title' => ['nullable', 'string', 'max:191'],
            'page_from' => ['required', 'integer', 'min:1'],
            'page_to' => ['required', 'integer', 'min:1', 'gte:page_from'],
        ]);

        $lesson = app(LessonBuilder::class)->build(
            $import,
            $data['lesson_title'],
            $data['chapter_title'] ?? null,
            $import->grade,
            (int) $data['page_from'],
            (int) $data['page_to'],
        );

        return redirect()
            ->to(route('ops.lessons.lesson', $lesson))
            ->with('success', 'درس پیش‌نویس ساخته شد: '.$lesson->lesson_title);
    }

    /* ------------------------------------------------------- the student's half */

    /**
     * One lesson, everything needed to get it in front of a student.
     *
     * The page is built around the question the operator actually has — "does
     * a student see this yet?" — because the pipeline's remaining failure mode
     * is a finished-looking lesson that nobody can open.
     */
    public function lesson(BookLesson $lesson): View
    {
        $lesson->load(['import', 'blocks', 'drafts']);

        return view('ops.lesson', [
            'lesson' => $lesson,
            'blocks' => $lesson->blocks,
            'drafts' => $lesson->drafts
                ->where('kind', LessonAiDraft::KIND_QUESTIONS)
                ->sortByDesc('id')
                ->values(),
            'questions' => Question::query()
                ->with(['answers' => fn ($query) => $query->orderBy('id')])
                ->where('lesson_id', $lesson->id)
                ->orderBy('id')
                ->get(),
            'lessonLabels' => StatusLabels::lesson(),
            'draftLabels' => StatusLabels::draft(),
            'aiEnabled' => LessonDrafter::questionsEnabled(),
            'releaseEnabled' => LessonDrafter::releaseEnabled(),
            'defaultQuestions' => max(3, (int) site('lessons.ai_questions_default', 5)),
            'maxQuestions' => max(3, (int) site('lessons.ai_questions_max', 12)),
        ]);
    }

    /**
     * Draft -> published, which is the ONLY thing that makes a lesson exist for
     * a student (`BookLesson::scopeVisibleTo`: status published AND grade
     * either null or equal to the student's).
     *
     * Grade is edited here rather than in a settings screen because it is the
     * difference between "published" and "published where somebody can see it"
     * — an empty value means every grade.
     */
    public function publish(Request $request, BookLesson $lesson): RedirectResponse
    {
        $data = $request->validate([
            'lesson_title' => ['required', 'string', 'max:191'],
            'grade' => ['nullable', 'string', 'max:32'],
        ]);

        $grade = trim((string) ($data['grade'] ?? ''));

        $lesson->forceFill([
            'lesson_title' => $data['lesson_title'],
            'grade' => $grade === '' ? null : $grade,
            'status' => 'published',
            'published_at' => $lesson->published_at ?? now(),
        ])->save();

        return back()->with('success', $grade === ''
            ? 'درس منتشر شد و برای همهٔ پایه‌ها دیده می‌شود.'
            : 'درس برای پایهٔ «'.$grade.'» منتشر شد.');
    }

    public function unpublish(BookLesson $lesson): RedirectResponse
    {
        $lesson->forceFill(['status' => 'draft'])->save();

        return back()->with('success', 'انتشار لغو شد؛ درس دوباره پیش‌نویس است.');
    }

    /**
     * Draft the lesson's questions with the AI, in THIS request.
     *
     * Same reasoning as extraction: the job body is reused exactly (it owns the
     * tenant binding, the status transitions, `payload = NULL` on failure), but
     * nobody has to remember to start a worker. The rate limit the staff
     * surface applies is not repeated here — this is a single operator, not a
     * tenant-facing endpoint.
     */
    public function ask(Request $request, BookLesson $lesson): RedirectResponse
    {
        abort_unless(LessonDrafter::questionsEnabled(), 404);

        $data = $request->validate([
            'count' => ['nullable', 'integer', 'min:3', 'max:'.max(3, (int) site('lessons.ai_questions_max', 12))],
        ]);

        $draft = LessonAiDraft::create([
            'lesson_id' => $lesson->id,
            'kind' => LessonAiDraft::KIND_QUESTIONS,
            'status' => 'queued',
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->runDraftNow($draft, $data['count'] ?? null);

        $fresh = $draft->fresh();
        $count = count($fresh->questions());

        return back()->with('success', $fresh->status === 'ready'
            ? 'تولید پرسش انجام شد: '.$count.' پرسش آمادهٔ بازبینی است.'
            : 'تولید پرسش به پایان نرسید؛ وضعیت پیش‌نویس را ببینید.');
    }

    /**
     * Approve a ready draft and write EVERY question into the lesson (and the
     * shared bank).
     *
     * The two-step state machine is kept — a draft is approved before anything
     * is written — but both steps happen in one attended action, because the
     * operator's intent is "these questions are good, put them in the lesson",
     * and making them click twice for that is what left the demo's quiz empty.
     */
    public function release(Request $request, BookLesson $lesson, LessonQuestionRelease $release): RedirectResponse
    {
        abort_unless(LessonDrafter::releaseEnabled(), 404);

        $data = $request->validate(['draft' => ['required', 'integer', 'min:1']]);

        $draft = LessonAiDraft::query()
            ->where('id', $data['draft'])
            ->where('lesson_id', $lesson->id)
            ->where('kind', LessonAiDraft::KIND_QUESTIONS)
            ->firstOrFail();

        abort_unless(in_array($draft->status, ['ready', 'approved'], true), 409, 'این پیش‌نویس قابل انتشار نیست.');

        if ($draft->status === 'ready') {
            $draft->forceFill(['status' => 'approved'])->save();
        }

        $total = count($draft->questions());
        $released = 0;

        for ($index = (int) $draft->released_count; $index < $total; $index++) {
            try {
                // `fresh()` per turn: the guard reads `released_count`, so a
                // stale model would either refuse a legitimate second question
                // or write one twice.
                $release->release($draft->fresh(), $lesson, $index);
                $released++;
            } catch (RuntimeException) {
                break;
            }
        }

        return back()->with('success', $released > 0
            ? $released.' پرسش به انتهای درس اضافه شد.'
            : 'پرسشی اضافه نشد.');
    }

    /** One rendered source page as a PNG. */
    public function page(LessonImport $import, int $page): BinaryFileResponse
    {
        $source = $import->sourcePages()->where('page_number', $page)->firstOrFail();

        $absolute = public_path('tenants/'.$import->tenant->slug.'/'.ltrim($source->image_path, '/'));

        abort_unless(is_file($absolute), 404);

        return response()->file($absolute, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Extract in THIS request.
     *
     * Reuses the job's body on purpose: the job owns the tenant binding, the
     * status transitions, the storage write and the work-directory cleanup.
     * Running it synchronously means those same guarantees apply without a
     * worker — and the operator sees the real outcome immediately.
     *
     * The time limit is lifted because a 400-page book legitimately takes
     * longer than PHP's default; this is an attended action, not a web request
     * a visitor waits on.
     */
    private function runNow(LessonImport $import): void
    {
        @set_time_limit(0);

        try {
            ExtractLessonPdf::for($import)->handle(
                app(PdfExtractor::class),
                app(LessonImportStorage::class),
            );
        } catch (Throwable $e) {
            // The job already wrote `failed` with a reason before re-throwing.
            // Reporting keeps it in the log; swallowing the throw keeps the
            // operator on a page that explains what happened instead of a 500.
            report($e);
        }
    }

    /** The AI draft, for the same reasons and with the same guarantees. */
    private function runDraftNow(LessonAiDraft $draft, ?int $count = null): void
    {
        @set_time_limit(0);

        try {
            GenerateLessonBlocks::forDraft($draft, $count)->handle(app(LessonDrafter::class));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
