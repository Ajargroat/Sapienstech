<?php

namespace App\Support\LessonPdf;

/**
 * What one extraction run produced, as plain data.
 *
 * Deliberately not Eloquent and not arrays-forever: the worker receives this
 * AFTER the python process finished, writes the DB rows from it, and never
 * touches the JSON file again. `error` is per-PDF, so one broken file in a
 * batch does not lose the others' pages.
 */
final class ExtractionResult
{
    /**
     * @param  list<array{page_number: int, char_count: int, flags: list<string>, image_path: ?string, width: ?int, height: ?int, paragraphs: list<string>}>  $pages
     * @param  list<array{xref: ?string, page_number: int, image_path: string, bbox: list<float>, width: ?int, height: ?int, confidence: string}>  $figures
     * @param  list<string>  $flags
     */
    public function __construct(
        public readonly int $pageCount = 0,
        public readonly string $structure = 'unknown',
        public readonly array $pages = [],
        public readonly array $figures = [],
        public readonly array $flags = [],
        public readonly int $figuresRejected = 0,
        public readonly ?string $error = null,
    ) {}

    /** @param array<string, mixed> $payload one PDF's entry from result.json */
    public static function fromArray(array $payload): self
    {
        $pages = [];
        foreach ((array) ($payload['pages'] ?? []) as $page) {
            $pages[] = [
                'page_number' => (int) ($page['page_number'] ?? 0),
                'char_count' => (int) ($page['char_count'] ?? 0),
                'flags' => array_values(array_map('strval', (array) ($page['flags'] ?? []))),
                'image_path' => isset($page['image_path']) ? (string) $page['image_path'] : null,
                'width' => isset($page['width']) ? (int) $page['width'] : null,
                'height' => isset($page['height']) ? (int) $page['height'] : null,
                'paragraphs' => array_values(array_map('strval', (array) ($page['paragraphs'] ?? []))),
            ];
        }

        $figures = [];
        foreach ((array) ($payload['figures'] ?? []) as $figure) {
            if (empty($figure['image_path'])) {
                continue;
            }
            $figures[] = [
                'xref' => isset($figure['xref']) && $figure['xref'] !== null ? (string) $figure['xref'] : null,
                'page_number' => (int) ($figure['page_number'] ?? 0),
                'image_path' => (string) $figure['image_path'],
                'bbox' => array_values(array_map('floatval', (array) ($figure['bbox'] ?? []))),
                'width' => isset($figure['crop_width']) ? (int) $figure['crop_width'] : null,
                'height' => isset($figure['crop_height']) ? (int) $figure['crop_height'] : null,
                'confidence' => (string) ($figure['confidence'] ?? 'high'),
            ];
        }

        $stats = (array) ($payload['figure_stats'] ?? []);

        return new self(
            pageCount: (int) ($payload['page_count'] ?? count($pages)),
            structure: (string) ($payload['structure'] ?? 'unknown'),
            pages: $pages,
            figures: $figures,
            flags: array_values(array_map('strval', (array) ($payload['flags'] ?? []))),
            figuresRejected: (int) ($stats['rejected'] ?? 0),
            error: isset($payload['error']) && $payload['error'] ? (string) $payload['error'] : null,
        );
    }

    /** The status a freshly extracted import lands in. */
    public function status(): string
    {
        if ($this->error !== null || $this->pages === []) {
            return 'failed';
        }

        return $this->structure === 'scanned' ? 'needs_ocr' : 'ready';
    }

    public function failed(): bool
    {
        return $this->status() === 'failed';
    }

    /** The text of one page, joined — what a lesson block is built from. */
    public function textForPage(int $pageNumber): string
    {
        foreach ($this->pages as $page) {
            if ($page['page_number'] === $pageNumber) {
                return implode("\n\n", $page['paragraphs']);
            }
        }

        return '';
    }

    /** Pages inside a lesson's range that carry usable text. */
    public function pagesWithText(int $from, int $to): array
    {
        return array_values(array_filter(
            $this->pages,
            fn (array $page) => $page['page_number'] >= $from && $page['page_number'] <= $to && $page['char_count'] > 0
        ));
    }

    /** @return array<string, mixed> the `stats` JSON stored on lesson_imports */
    public function stats(): array
    {
        return [
            'page_count' => $this->pageCount,
            'pages' => array_map(fn (array $page) => [
                'page' => $page['page_number'],
                'chars' => $page['char_count'],
                'flags' => $page['flags'],
            ], $this->pages),
            'figures_accepted' => count($this->figures),
            'figures_rejected' => $this->figuresRejected,
            'flags' => $this->flags,
        ];
    }
}
