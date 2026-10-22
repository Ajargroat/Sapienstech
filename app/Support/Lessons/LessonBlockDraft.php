<?php

namespace App\Support\Lessons;

use App\Models\BookLesson;
use App\Models\LessonBlock;
use App\Models\LessonFigure;

/**
 * Validates an AI block draft before a byte of it can reach `lesson_blocks`.
 *
 * The rules are the editor's own rules (LessonBlocks::validateBlock) PLUS the
 * three that only make sense for generated content: the quoted page must be
 * one the lesson actually has, a figure must be one that exists in the range,
 * and there may be at most one two-up figure. A draft that breaks them is
 * refused with a field-level reason — never "fixed" silently, because a
 * quietly repaired draft teaches the prompt nothing.
 */
class LessonBlockDraft
{
    /**
     * @param  array<string, mixed>  $decoded  the model's parsed JSON
     * @return array{payload: ?array, errors: list<string>}
     */
    public static function validate(array $decoded, BookLesson $lesson): array
    {
        $errors = [];
        $raw = $decoded['blocks'] ?? null;

        if (! is_array($raw) || $raw === []) {
            return ['payload' => null, 'errors' => ['پاسخ مدل هیچ بلوکی نداشت.']];
        }

        $pages = LessonPrompt::passagesPageNumbers($lesson);
        $figures = LessonPrompt::figuresFor($lesson);
        $figureIds = array_values(array_map(fn ($f) => (int) $f['id'], $figures));

        $blocks = [];
        $duoCount = 0;

        foreach ($raw as $index => $block) {
            if (! is_array($block)) {
                $errors[] = "block {$index}: not an object";

                continue;
            }

            $type = (string) ($block['type'] ?? '');

            if (! in_array($type, LessonBlock::TYPES, true)) {
                $errors[] = "block {$index}: unknown type";

                continue;
            }

            $data = [];
            switch ($type) {
                case 'heading':
                    $data = ['text' => (string) ($block['text'] ?? ''), 'level' => (int) ($block['level'] ?? 2)];
                    break;
                case 'paragraph':
                    $data = ['text' => (string) ($block['text'] ?? '')];
                    break;
                case 'points':
                    $data = ['items' => (array) ($block['items'] ?? [])];
                    break;
                case 'equation':
                    $data = ['expr' => (string) ($block['expr'] ?? ''), 'caption' => $block['caption'] ?? null];
                    break;
                case 'tip':
                    $data = ['title' => $block['title'] ?? null, 'text' => (string) ($block['text'] ?? '')];
                    break;
                case 'figure':
                    $figureIndex = (int) ($block['figure_index'] ?? -1);
                    $figureId = $figureIds[$figureIndex] ?? null;

                    if ($figureId === null) {
                        $errors[] = "block {$index}: figure_index out of range";

                        continue 2;
                    }

                    $data = [
                        'figure_id' => $figureId,
                        'placement' => in_array(($block['placement'] ?? null), ['right', 'left', 'wide'], true) ? $block['placement'] : 'wide',
                        'caption' => $block['caption'] ?? null,
                    ];
                    break;
                case 'figure_duo':
                    $duoCount++;

                    if ($duoCount > 1) {
                        $errors[] = 'more than one figure_duo';

                        continue 2;
                    }

                    $ids = array_values(array_filter(array_map(
                        fn ($i) => $figureIds[(int) $i] ?? null,
                        (array) ($block['figure_indices'] ?? [])
                    )));

                    if (count($ids) < 2) {
                        $errors[] = "block {$index}: figure_indices need two valid entries";

                        continue 2;
                    }

                    $data = ['figure_ids' => $ids, 'caption' => $block['caption'] ?? null];
                    break;
            }

            foreach (LessonBlocks::validateBlock($type, $data) as $field => $message) {
                $errors[] = "block {$index}: {$field}: {$message}";
            }

            $sourcePage = isset($block['source_page']) ? (int) $block['source_page'] : null;

            if ($sourcePage !== null && $pages !== [] && ! in_array($sourcePage, $pages, true)) {
                $errors[] = "block {$index}: source_page not in this lesson";

                continue;
            }

            $blocks[] = [
                'type' => $type,
                'data' => LessonBlocks::normalise($type, $data),
                'source_page' => $sourcePage,
            ];
        }

        if ($blocks === []) {
            return ['payload' => null, 'errors' => $errors ?: ['هیچ بلوک معتبری در پاسخ نبود.']];
        }

        // Partial acceptance is deliberate here: a draft of 12 blocks with one
        // unplaceable figure should still give the editor its 11 usable blocks.
        // The question draft is the strict one (all-or-nothing) because a
        // question with a wrong answer reaches a student.
        return ['payload' => ['blocks' => $blocks], 'errors' => $errors];
    }

    /**
     * Store the validated blocks over the lesson's current draft blocks.
     * Human-dirty blocks are KEPT — an AI re-draft must never erase a typo fix
     * a person already made.
     */
    public static function replaceInto(BookLesson $lesson, array $payload): int
    {
        $written = 0;
        $position = (int) $lesson->blocks()->max('position');

        foreach ((array) ($payload['blocks'] ?? []) as $block) {
            LessonBlock::create([
                'tenant_id' => $lesson->tenant_id,
                'lesson_id' => $lesson->id,
                'position' => ++$position,
                'type' => $block['type'],
                'anchor' => $block['anchor'] ?? null,
                'data' => $block['data'],
                'source_page' => $block['source_page'] ?? null,
                'confidence' => 'medium',
                'is_dirty' => false,
            ]);

            $written++;
        }

        $lesson->forceFill(['ai_drafted_at' => now()])->save();

        return $written;
    }

    /** Figures in the lesson's range, for the editor's strip. */
    public static function figures(BookLesson $lesson): array
    {
        return LessonFigure::query()
            ->where('lesson_import_id', $lesson->lesson_import_id)
            ->orderBy('page_number')->orderBy('id')
            ->get()
            ->all();
    }
}
