<?php

namespace App\Http\Controllers\Lessons;

use App\Http\Controllers\Controller;
use App\Models\BookLesson;
use App\Models\LessonBlock;
use App\Models\LessonFigure;
use App\Models\LessonSourcePage;
use App\Support\Lessons\LessonAccess;
use App\Support\Lessons\LessonBlocks;
use App\Support\Lessons\StatusLabels;
use App\Support\TenantUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The staff block editor — the "contractor editor" the user asked for: add,
 * reorder, delete and retype blocks beside the book's own page image, fix a
 * typo, drop in a نکته box, drag a cropped figure into the flow.
 *
 * The list is REPLACE-WHOLESALE (AGENTS §9): one POST carries the whole
 * ordered list and the server renumbers it, so a drag is a single write and a
 * concurrent save cannot interleave two orderings.
 */
class LessonEditorController extends Controller
{
    public function edit(BookLesson $lesson): View
    {
        LessonAccess::authorizeStaff($lesson);

        $lesson->load(['blocks.figure', 'import.sourcePages']);

        $figures = LessonFigure::query()
            ->where('lesson_import_id', $lesson->lesson_import_id)
            ->whereBetween('page_number', [
                $lesson->source_page_from ?: 1,
                $lesson->source_page_to ?: PHP_INT_MAX,
            ])
            ->orderBy('page_number')->orderBy('id')->get();

        $blockTypeLabels = StatusLabels::blockTypes();

        // The same range the Blade fallback uses; computed once here so the
        // island and the server-rendered rows can never disagree about which
        // book pages belong to this lesson.
        $pagesInRange = ($lesson->import?->sourcePages ?? collect())
            ->filter(fn ($sourcePage) => $sourcePage->page_number >= ($lesson->source_page_from ?: 0)
                && $sourcePage->page_number <= ($lesson->source_page_to ?: PHP_INT_MAX))
            ->values();

        /*
         | One server-serialised props payload for the React island
         | (resources/js/features/consultant-lesson-editor.js). The Blade rows
         | below stay as the pre-mount / JS-off fallback: they post the same
         | field names, so a browser without JS still saves a valid list.
         |
         | Everything is derived from the already-loaded relations; there is no
         | fetch endpoint, which is what keeps this page a single server render
         | and lets the page router swap it like any other lesson screen.
         */
        $editorPayload = [
            'lessonId' => $lesson->id,
            'status' => $lesson->status,
            'saveUrl' => lessons_route('editor.blocks', $lesson),
            'csrfToken' => csrf_token(),
            'blocks' => $lesson->blocks->values()->map(fn (LessonBlock $block) => [
                'id' => $block->id,
                'type' => $block->type,
                'data' => (array) ($block->data ?? []),
                'sourcePage' => $block->source_page,
                'isDirty' => (bool) $block->is_dirty,
                'anchor' => $block->anchor,
                'figureUrl' => $block->type === 'figure' && $block->figure
                    ? TenantUploads::url($block->figure->image_path)
                    : null,
            ])->all(),
            'figures' => $figures->map(fn (LessonFigure $figure) => [
                'id' => $figure->id,
                'page' => $figure->page_number,
                'url' => TenantUploads::url($figure->image_path),
                'confidence' => $figure->confidence,
            ])->all(),
            'pages' => $pagesInRange->map(fn (LessonSourcePage $sourcePage) => [
                'number' => $sourcePage->page_number,
                'url' => lessons_route('import.page', [$lesson->import, $sourcePage->page_number]),
            ])->all(),
            'types' => collect($blockTypeLabels)
                ->map(fn (string $label, string $type) => ['value' => $type, 'label' => $label])
                ->values()->all(),
            'placements' => [
                ['value' => 'wide', 'label' => 'تمام‌عرض'],
                ['value' => 'right', 'label' => 'کنار متن (راست)'],
                ['value' => 'left', 'label' => 'کنار متن (چپ)'],
            ],
            'errors' => $this->validationErrorsForPayload(),
        ];

        return view('lessons.editor', [
            'lesson' => $lesson,
            'blocks' => $lesson->blocks,
            'figures' => $figures,
            'page' => 'lessons',
            'blockTypeLabels' => $blockTypeLabels,
            'statusLabels' => StatusLabels::lesson(),
            'draftLabels' => StatusLabels::draft(),
            'pages' => $lesson->import?->sourcePages ?? collect(),
            'aiBlocksEnabled' => (bool) site('lessons.ai_blocks_enabled', false),
            'aiQuestionsEnabled' => (bool) site('lessons.ai_questions_enabled', false),
            'lastBlockDraft' => $lesson->drafts()->where('kind', 'blocks')->first(),
            'lastQuestionDraft' => $lesson->drafts()->where('kind', 'questions')->first(),
            'editorPayload' => $editorPayload,
        ]);
    }

    /**
     * Replace the lesson's block list with the posted one.
     *
     * `positions` are ignored on purpose: the array order IS the order, which
     * is what a drag-and-drop DOM produces and what makes renumbering a single
     * pass instead of a swap dance.
     */
    public function storeBlocks(Request $request, BookLesson $lesson): RedirectResponse
    {
        LessonAccess::authorizeStaff($lesson);

        $data = $request->validate([
            'blocks' => ['array'],
            'blocks.*.id' => ['nullable', 'integer'],
            'blocks.*.type' => ['required', 'string', 'max:16'],
            'blocks.*.data' => ['required', 'array'],
            'blocks.*.source_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $submitted = (array) ($data['blocks'] ?? []);

        // Validate every block BEFORE writing, and report the field paths the
        // editor can point at — a 422, never a 500 (PREFERENCE §4.1).
        $errors = [];

        foreach ($submitted as $index => $block) {
            $type = (string) ($block['type'] ?? '');
            $blockData = (array) ($block['data'] ?? []);

            foreach (LessonBlocks::validateBlock($type, $blockData) as $field => $message) {
                $errors["blocks.{$index}.data.{$field}"] = $message;
            }

            if ($type === 'figure' && ! empty($blockData['figure_id'])) {
                $owns = LessonFigure::query()
                    ->whereKey((int) $blockData['figure_id'])
                    ->where('lesson_import_id', $lesson->lesson_import_id)
                    ->exists();

                // A figure from another import is 404 territory, not "fix it".
                abort_unless($owns, 404);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($lesson, $submitted) {
            $existing = $lesson->blocks()->get()->keyBy('id');
            $keptIds = [];

            foreach ($submitted as $position => $block) {
                $type = (string) $block['type'];
                $normalised = LessonBlocks::normalise($type, (array) $block['data']);
                $id = isset($block['id']) ? (int) $block['id'] : null;
                $current = $id ? $existing->get($id) : null;

                // Ownership: a block id from another lesson is not ours to move.
                abort_if($id && ! $current, 404);

                $isDirty = $current
                    ? ($current->is_dirty || LessonBlocks::fingerprint($current->type, (array) $current->data) !== LessonBlocks::fingerprint($type, $normalised))
                    : true;

                $anchor = $type === 'heading'
                    ? ($current?->anchor ?: $this->anchorFor($normalised, $position))
                    : null;

                // Keep the AI's comparison baseline in sync with the stored
                // value, so the NEXT save compares against what a human left.
                $attributes = [
                    'position' => $position,
                    'type' => $type,
                    'anchor' => $anchor,
                    'data' => $normalised,
                    'source_page' => $block['source_page'] ?? $current?->source_page,
                    'is_dirty' => $isDirty,
                    /*
                     | The column, not only data.figure_id: the fallback row and
                     | the island preview both read the relation, so a picked
                     | figure lost its thumbnail after the first save without
                     | this line.
                     */
                    'figure_id' => $type === 'figure' && ! empty($normalised['figure_id'])
                        ? (int) $normalised['figure_id']
                        : null,
                ];

                if ($current) {
                    $current->fill($attributes)->save();
                    $keptIds[] = $current->id;

                    continue;
                }

                $created = LessonBlock::create([
                    'tenant_id' => $lesson->tenant_id,
                    'lesson_id' => $lesson->id,
                    'confidence' => null,
                ] + $attributes);

                $keptIds[] = $created->id;
            }

            // Lists replace wholesale: anything not in the submitted list goes.
            $lesson->blocks()->whereNotIn('id', $keptIds)->delete();
        });

        return redirect()
            ->to(lessons_route('editor.edit', $lesson))
            ->with('success', 'تغییرها ثبت شد.');
    }

    /** Publish (draft → published), one decision in one transaction. */
    public function publish(BookLesson $lesson): RedirectResponse
    {
        LessonAccess::authorizeStaff($lesson);

        if ($lesson->blocks()->count() === 0) {
            throw ValidationException::withMessages([
                'blocks' => 'درسی که هیچ بلوکی ندارد منتشر نمی‌شود.',
            ]);
        }

        DB::transaction(function () use ($lesson) {
            $lesson->forceFill([
                'status' => 'published',
                'published_at' => $lesson->published_at ?? now(),
            ])->save();
        });

        return redirect()
            ->to(lessons_route('editor.edit', $lesson))
            ->with('success', 'درس منتشر شد.');
    }

    /** Take a published lesson back to draft so it can be fixed. */
    public function revise(BookLesson $lesson): RedirectResponse
    {
        LessonAccess::authorizeStaff($lesson);

        $lesson->forceFill(['status' => 'draft'])->save();

        return redirect()
            ->to(lessons_route('editor.edit', $lesson))
            ->with('success', 'درس به پیش‌نویس برگشت.');
    }

    /** The figure strip for the editor (JSON, so the JS can refilter it). */
    public function figures(BookLesson $lesson): JsonResponse
    {
        LessonAccess::authorizeStaff($lesson);

        return response()->json([
            'figures' => LessonFigure::query()
                ->where('lesson_import_id', $lesson->lesson_import_id)
                ->orderBy('page_number')->orderBy('id')
                ->get()
                ->map(fn (LessonFigure $figure) => [
                    'id' => $figure->id,
                    'page' => $figure->page_number,
                    'url' => TenantUploads::url($figure->image_path),
                    'confidence' => $figure->confidence,
                ]),
        ]);
    }

    /**
     * Flatten the flashed ValidationException bag to one message per key so
     * the island can render the same `blocks.N.data.field` spans the Blade
     * fallback renders (PREFERENCE §4.1: rejections point at the input).
     */
    private function validationErrorsForPayload(): array
    {
        $bag = session()->get('errors');

        if (! $bag instanceof ViewErrorBag) {
            return [];
        }

        $messages = [];

        foreach ($bag->getBag('default')->toArray() as $key => $list) {
            $messages[$key] = (string) (is_array($list) ? reset($list) : $list);
        }

        return $messages;
    }

    private function anchorFor(array $data, int $position): string
    {
        $slug = (string) preg_replace('/[^A-Za-z0-9-]+/', '-', Str::lower(Str::slug((string) ($data['text'] ?? ''))));

        return mb_substr($slug !== '' ? $slug : 'section-'.$position, 0, 64);
    }
}
