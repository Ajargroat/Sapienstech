<?php

namespace App\Http\Controllers\Lessons;

use App\Http\Controllers\Controller;
use App\Jobs\ExtractLessonPdf;
use App\Models\BookLesson;
use App\Models\LessonImport;
use App\Support\LessonBuilder;
use App\Support\Lessons\LessonAccess;
use App\Support\Lessons\StatusLabels;
use App\Support\TenantUploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The staff import surface: upload a book PDF, watch the extraction, build a
 * draft lesson from a page range, and open a source page as a PNG.
 *
 * Mounted twice (consultant + teacher), so NOTHING here names a prefix: links
 * go through lessons_route(), authorization through LessonAccess.
 */
class LessonImportController extends Controller
{
    public function index(): View
    {
        // The staff entry point binds no {lesson}/{import}, so no downstream
        // check protects it. `web` + a tenant feature flag is NOT sufficient
        // (a student-guard session also runs through `web`), and listing a
        // tenant's book imports is staff information — so authorize here.
        abort_unless(LessonAccess::staffCanAuthor(), 404);

        $imports = LessonImport::query()
            ->withCount(['sourcePages', 'figures', 'lessons'])
            ->orderByDesc('id')
            ->get();

        /*
         | The staff landing page lists the books; the lessons authored from
         | them are the thing a consultant actually checks before a student
         | sees it. Without this list the only way to a lesson was the import
         | detail page, so a published lesson had no visible entry point at
         | all. Scoped to the tenant by BelongsToTenant.
         */
        $lessons = BookLesson::query()
            ->whereIn('status', ['draft', 'published'])
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get();

        return view('lessons.import', [
            'imports' => $imports,
            'lessons' => $lessons,
            'page' => 'lessons',
            'statusLabels' => StatusLabels::import(),
            'lessonStatusLabels' => StatusLabels::lesson(),
            'maxPages' => (int) site('lessons.max_pages', 400),
            'authoring' => LessonAccess::staffCanAuthor(),
            'canPreview' => LessonAccess::staffCanAuthor(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(LessonAccess::staffCanAuthor(), 404);

        $maxKb = max(1024, (int) site('academics.materials.max_kb', 20480));

        $data = $request->validate([
            'book_title' => ['required', 'string', 'max:191'],
            'grade' => ['nullable', 'string', 'max:32'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.$maxKb],
        ]);

        $folder = 'lessons/pdf';

        $import = LessonImport::create([
            'book_title' => $data['book_title'],
            'grade' => $data['grade'] ?? null,
            'pdf_path' => TenantUploads::store($request->file('file'), $folder),
            'status' => 'queued',
            'created_by_user_id' => $request->user()->id,
        ]);

        ExtractLessonPdf::dispatch($import->tenant_id, $import->id);

        return redirect()
            ->to(lessons_route('import.show', $import))
            ->with('success', 'کتاب بارگذاری شد؛ استخراج در صف قرار گرفت.');
    }

    public function show(LessonImport $import): View
    {
        $this->authorizeImport($import);

        $import->load(['sourcePages', 'figures', 'lessons']);

        return view('lessons.import-show', [
            'import' => $import,
            'page' => 'lessons',
            'statusLabels' => StatusLabels::import(),
            'structureLabels' => StatusLabels::structure(),
            'running' => $import->isRunning(),
            'figures' => $import->figures->groupBy('page_number'),
            'pages' => $import->sourcePages->groupBy('page_number'),
        ]);
    }

    /** One rendered source page as a PNG, for the editor's left pane. */
    public function page(LessonImport $import, int $page): BinaryFileResponse
    {
        $this->authorizeImport($import);

        $source = $import->sourcePages()->where('page_number', $page)->firstOrFail();

        $absolute = public_path('tenants/'.$import->tenant->slug.'/'.ltrim($source->image_path, '/'));

        abort_unless(is_file($absolute), 404);

        return response()->file($absolute, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** Turn a page range into a draft lesson full of text blocks. */
    public function build(Request $request, LessonImport $import): RedirectResponse
    {
        $this->authorizeImport($import);

        abort_unless($import->status === 'ready', 422, 'The import has no usable text to build from.');

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
            ->to(lessons_route('editor.edit', $lesson))
            ->with('success', 'درس پیش‌نویس ساخته شد.');
    }

    protected function authorizeImport(LessonImport $import): void
    {
        abort_unless(LessonAccess::staffCanAuthor(), 404);
        abort_unless((int) $import->tenant_id === (int) (tenant()?->id), 404);
    }
}
