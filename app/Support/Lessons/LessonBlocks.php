<?php

namespace App\Support\Lessons;

use App\Models\LessonBlock;

/**
 * The block contract: which types exist and what each type's payload must
 * contain, validated SERVER-side.
 *
 * Two callers share it, and that is the point: the editor's "ثبت تغییرات"
 * posts a whole ordered list, and the AI block drafter's reply is validated
 * with the same rules before it is stored as a draft. There is therefore no
 * second, looser path into lesson_blocks — an AI block that a human could not
 * have typed is refused.
 */
class LessonBlocks
{
    /** Nesting/shape guard: a block payload is small. */
    private const MAX_TEXT = 8000;

    private const MAX_POINTS = 20;

    /**
     * Validate ONE block.
     *
     * @param  array<string, mixed>  $data
     * @return list<string> field-level errors, empty when valid
     */
    public static function validateBlock(string $type, array $data, int $figuresAvailable = 0): array
    {
        $errors = [];

        if (! in_array($type, LessonBlock::TYPES, true)) {
            return ['type' => 'نوع بلوک ناشناخته است.'];
        }

        switch ($type) {
            case 'heading':
                if (trim((string) ($data['text'] ?? '')) === '') {
                    $errors['text'] = 'متن تیتر لازم است.';
                }
                break;

            case 'paragraph':
                if (trim((string) ($data['text'] ?? '')) === '') {
                    $errors['text'] = 'متن پاراگراف لازم است.';
                }
                break;

            case 'points':
                $items = $data['items'] ?? null;

                if (! is_array($items) || $items === []) {
                    $errors['items'] = 'دست‌کم یک نکته لازم است.';
                    break;
                }

                if (count($items) > self::MAX_POINTS) {
                    $errors['items'] = 'تعداد نکته‌ها بیش از حد مجاز است.';
                }

                foreach ($items as $i => $item) {
                    if (! is_array($item) || trim((string) ($item['text'] ?? '')) === '') {
                        $errors["items.{$i}"] = 'متن نکته لازم است.';
                    }
                }
                break;

            case 'equation':
                if (trim((string) ($data['expr'] ?? '')) === '') {
                    $errors['expr'] = 'عبارت ریاضی لازم است.';
                }
                break;

            case 'tip':
                if (trim((string) ($data['text'] ?? '')) === '') {
                    $errors['text'] = 'متن نکته لازم است.';
                }
                break;

            case 'figure':
                $figureId = $data['figure_id'] ?? null;

                if ($figureId === null) {
                    $errors['figure_id'] = 'یک تصویر انتخاب کنید.';
                }
                break;

            case 'figure_duo':
                $ids = $data['figure_ids'] ?? [];

                if (! is_array($ids) || count(array_filter($ids)) < 2) {
                    $errors['figure_ids'] = 'دو تصویر لازم است.';
                }
                break;
        }

        foreach (['text', 'caption', 'title', 'expr'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && mb_strlen($data[$key]) > self::MAX_TEXT) {
                $errors[$key] = 'متن بیش از حد بلند است.';
            }
        }

        if (isset($data['placement']) && ! in_array($data['placement'], ['right', 'left', 'wide'], true)) {
            $errors['placement'] = 'جای تصویر نامعتبر است.';
        }

        return $errors;
    }

    /**
     * The canonical form stored in the DB, so an unchanged block compares
     * equal to the AI's payload (that comparison is what sets `is_dirty`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalise(string $type, array $data): array
    {
        return match ($type) {
            'heading' => array_filter([
                'text' => trim((string) ($data['text'] ?? '')),
                'level' => (int) ($data['level'] ?? 2),
            ], fn ($v) => $v !== null),
            'paragraph' => array_filter([
                'text' => trim((string) ($data['text'] ?? '')),
                'width' => in_array(($data['width'] ?? null), ['full'], true) ? 'full' : null,
            ], fn ($v) => $v !== null),
            'points' => [
                'items' => array_values(array_map(fn ($item) => array_filter([
                    'icon' => isset($item['icon']) && trim((string) $item['icon']) !== '' ? trim((string) $item['icon']) : null,
                    'lead' => isset($item['lead']) && trim((string) $item['lead']) !== '' ? trim((string) $item['lead']) : null,
                    'text' => trim((string) ($item['text'] ?? '')),
                ], fn ($v) => $v !== null), (array) ($data['items'] ?? []))),
            ],
            'equation' => array_filter([
                'expr' => trim((string) ($data['expr'] ?? '')),
                'caption' => isset($data['caption']) && trim((string) $data['caption']) !== '' ? trim((string) $data['caption']) : null,
            ], fn ($v) => $v !== null),
            'tip' => array_filter([
                'title' => isset($data['title']) && trim((string) $data['title']) !== '' ? trim((string) $data['title']) : null,
                'text' => trim((string) ($data['text'] ?? '')),
            ], fn ($v) => $v !== null),
            'figure' => array_filter([
                'figure_id' => isset($data['figure_id']) ? (int) $data['figure_id'] : null,
                'placement' => in_array(($data['placement'] ?? null), ['right', 'left', 'wide'], true) ? $data['placement'] : 'wide',
                'caption' => isset($data['caption']) && trim((string) $data['caption']) !== '' ? trim((string) $data['caption']) : null,
            ], fn ($v) => $v !== null),
            'figure_duo' => array_filter([
                'figure_ids' => array_values(array_map('intval', (array) ($data['figure_ids'] ?? []))),
                'caption' => isset($data['caption']) && trim((string) $data['caption']) !== '' ? trim((string) $data['caption']) : null,
            ], fn ($v) => $v !== null),
            default => [],
        };
    }

    /**
     * A stable comparison key for `is_dirty`: key order must never matter,
     * because the AI payload and the edited payload arrive from two different
     * encoders.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fingerprint(string $type, array $data): string
    {
        return hash('sha256', $type.'|'.self::canonicalJson(self::normalise($type, $data)));
    }

    /**
     * The plain text of a block — what the TOC, the reading meter and the
     * MCQ validator's "is this quote from the passage" check read.
     */
    public static function plainText(array $block): string
    {
        $data = (array) ($block['data'] ?? $block);
        $type = (string) ($block['type'] ?? '');

        return match ($type) {
            'heading', 'paragraph', 'tip' => (string) ($data['text'] ?? ''),
            'points' => implode(' ', array_map(fn ($item) => trim((string) ($item['lead'] ?? '').' '.(string) ($item['text'] ?? '')), (array) ($data['items'] ?? []))),
            'equation' => (string) ($data['expr'] ?? ''),
            default => '',
        };
    }

    /** @param array<string, mixed> $value */
    private static function canonicalJson(array $value): string
    {
        $sort = function ($item) use (&$sort) {
            if (! is_array($item)) {
                return $item;
            }

            if (array_keys($item) !== range(0, count($item) - 1)) {
                ksort($item);
            }

            return array_map($sort, $item);
        };

        return (string) json_encode($sort($value), JSON_UNESCAPED_UNICODE);
    }
}
