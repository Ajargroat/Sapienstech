<?php

namespace App\Support;

use App\Models\BookLesson;
use App\Models\LessonBlock;
use App\Models\LessonFigure;
use App\Models\LessonImport;
use App\Models\LessonSourcePage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turning an extracted import into a draft lesson.
 *
 * The book's own text is the source of truth, so the builder does NOT invent
 * structure: every paragraph the extractor recovered becomes a paragraph
 * block, and the first paragraph of each page whose character count says
 * "this looks like a heading line" becomes a heading. Figures are NOT placed
 * automatically (a misplaced crop is worse than a missing one) — they are
 * attached by a human in the editor, or in a block the AI draft proposes.
 *
 * Confidence is the honest part: any block whose page carried a low-text or
 * mode-disagreement flag is written `medium`, which is exactly what makes the
 * editor open on the page image for it.
 */
class LessonBuilder
{
    public function build(
        LessonImport $import,
        string $title,
        ?string $chapterTitle = null,
        ?string $grade = null,
        ?int $from = null,
        ?int $to = null,
    ): BookLesson {
        $pages = $import->sourcePages()->get();

        $from ??= (int) ($pages->min('page_number') ?: 1);
        $to ??= (int) ($pages->max('page_number') ?: $from);

        return DB::transaction(function () use ($import, $title, $chapterTitle, $grade, $from, $to) {
            $lesson = BookLesson::create([
                'tenant_id' => $import->tenant_id,
                'lesson_import_id' => $import->id,
                'book_title' => $import->book_title,
                'chapter_title' => $chapterTitle,
                'lesson_title' => $title,
                'subject' => null,
                'grade' => $grade ?? $import->grade,
                'source_page_from' => $from,
                'source_page_to' => $to,
                'status' => 'draft',
            ]);

            $position = 0;

            foreach ($this->blocksFor($import, $from, $to) as $block) {
                LessonBlock::create([
                    'tenant_id' => $import->tenant_id,
                    'lesson_id' => $lesson->id,
                    'position' => $position++,
                    'type' => $block['type'],
                    'anchor' => $block['anchor'] ?? null,
                    'data' => $block['data'],
                    'source_page' => $block['source_page'],
                    'confidence' => $block['confidence'],
                    'is_dirty' => false,
                ]);
            }

            // The figures in this range belong to this lesson now; they are
            // offered in the editor's figure strip.
            LessonFigure::query()
                ->where('lesson_import_id', $import->id)
                ->whereBetween('page_number', [$from, $to])
                ->update(['linked_lesson_id' => $lesson->id]);

            return $lesson;
        });
    }

    /**
     * The import's text as ordered blocks, page by page.
     *
     * @return list<array{type: string, data: array<string, mixed>, source_page: int, confidence: string, anchor?: string}>
     */
    public function blocksFor(LessonImport $import, int $from, int $to): array
    {
        $blocks = [];
        $anchorsUsed = [];

        /** @var LessonSourcePage $page */
        foreach ($import->sourcePages()->whereBetween('page_number', [$from, $to])->get() as $page) {
            $paragraphs = $this->paragraphsOf($page);
            $confidence = $this->confidenceFor($page);

            foreach ($paragraphs as $index => $text) {
                $text = trim($text);

                if ($text === '') {
                    continue;
                }

                // A short first line of a page is the page's own heading.
                $isHeading = $index === 0 && mb_strlen($text) <= 90 && ! str_ends_with($text, '.');

                if ($isHeading) {
                    $anchor = $this->anchor($text, $anchorsUsed);
                    $blocks[] = [
                        'type' => 'heading',
                        'data' => ['text' => $text, 'level' => 2],
                        'source_page' => $page->page_number,
                        'confidence' => $confidence,
                        'anchor' => $anchor,
                    ];

                    continue;
                }

                $blocks[] = [
                    'type' => 'paragraph',
                    'data' => ['text' => $text],
                    'source_page' => $page->page_number,
                    'confidence' => $confidence,
                ];
            }
        }

        return $blocks;
    }

    /** @return list<string> */
    private function paragraphsOf(LessonSourcePage $page): array
    {
        // The extractor's own paragraphs, stored beside the page. When a page
        // has none (a scanned page) it contributes no text block at all — the
        // editor shows the page image and a human types.
        $paragraphs = (array) ($page->paragraphs ?? []);

        if (! is_array($paragraphs)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($p) => (string) $p, $paragraphs), fn ($p) => trim($p) !== ''));
    }

    private function confidenceFor(LessonSourcePage $page): string
    {
        $flags = (array) $page->flags;

        foreach (['no_text_layer', 'low_text', 'mode_disagreement', 'axis_ambiguous', 'single_line', 'text_failed'] as $flag) {
            if (in_array($flag, $flags, true)) {
                return 'medium';
            }
        }

        return 'high';
    }

    /**
     * A stable, unique slug for a heading — the reader's TOC target. Persian
     * text has no ASCII slug, so the anchor is derived from the page/order and
     * a latinised tail, never from raw Persian (URL fragments must survive
     * every browser and the page-router).
     *
     * @param  array<string, int>  $used
     */
    private function anchor(string $text, array &$used): string
    {
        $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', Str::lower(Str::slug($text))) ?: 'section';
        $slug = mb_substr($slug, 0, 40) ?: 'section';

        if (! isset($used[$slug])) {
            $used[$slug] = 1;

            return $slug;
        }

        return $slug.'-'.(++$used[$slug]);
    }
}
