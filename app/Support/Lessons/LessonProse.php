<?php

namespace App\Support\Lessons;

/**
 * Reads the questions a model wrote as PROSE instead of JSON.
 *
 * Why this exists: the prompt asks for a JSON object, and the configured model
 * answers with real, usable questions in Persian markdown. Two shapes have been
 * observed in production, so both are handled:
 *
 *     سؤال ۱: آدنوزین در کلیه چه اثری دارد؟      |  **سؤال ۱**
 *     - الف) افزایش جریان خون                    |  مصرف ناپروکسن با کدام خطر همراه است؟
 *     - ب) کاهش جریان خون و کاهش GFR ✓           |  الف) هیپوکالمی شدید
 *                                                |  ب) هیپرکالمی و آریتمی قلبی
 *                                                |  پاسخ صحیح: **ب**
 *
 * `LessonDrafter::extractJson()` finds no object in either, so before this
 * reader the draft was recorded as "the model returned no questions" and a
 * usable set was thrown away — which is why the lesson page had no quiz even
 * though the AI half had run.
 *
 * Two rules keep this honest rather than merely lenient:
 *  1. a question is kept only when part of its stem really occurs in the
 *     lesson's own passage — `source_quote` is taken FROM the passage, never
 *     invented, because that is what grounds the question in the book;
 *  2. the shape is never guessed: exactly four options and exactly one answer
 *     (a ✓ mark, or a "پاسخ صحیح: ب" line). Anything else is dropped.
 */
final class LessonProse
{
    private const MIN_QUOTE = 8;

    /** @var array<string, int> option letter → index */
    private const LETTERS = [
        'الف' => 0, 'ا' => 0, 'a' => 0,
        'ب' => 1, 'b' => 1,
        'ج' => 2, 'c' => 2,
        'د' => 3, 'd' => 3,
    ];

    /**
     * @return array{questions: list<array<string, mixed>>}
     */
    public static function parse(string $raw, string $passage = ''): array
    {
        $normalisedPassage = LessonQuestionValidator::normalise($passage);
        $lines = preg_split('/\R/u', $raw) ?: [];

        $questions = [];
        $stem = [];
        $options = [];
        $answer = null;

        $flush = function () use (&$questions, &$stem, &$options, &$answer, $normalisedPassage): void {
            $built = self::build(implode(' ', $stem), $options, $answer, $normalisedPassage);

            if ($built !== null) {
                $questions[] = $built;
            }

            $stem = [];
            $options = [];
            $answer = null;
        };

        foreach ($lines as $line) {
            $line = self::tidy($line);

            if ($line === '') {
                continue;
            }

            // "**سؤال ۱**" is a header with no payload; "سؤال ۱: …" carries the
            // stem on the same line. Both start a question.
            if (preg_match('/^(?:سؤال|سوال|پرسش|question)\s*[۰-۹0-9]*\s*[:：.\-–]?\s*(.*)$/u', $line, $m) === 1) {
                $flush();

                if (trim($m[1]) !== '') {
                    $stem[] = trim($m[1]);
                }

                continue;
            }

            if (preg_match('/^(?:پاسخ|جواب)\s*(?:صحیح|درست|نهایی)?\s*[:：]?\s*(.+)$/u', $line, $m) === 1) {
                $answer = self::letter($m[1]);

                continue;
            }

            // A numbered stem with no header word ("1) … ؟") starts a question
            // too. It is only a NEW question once the previous one has options,
            // so a stem that ends with a question mark is never split in two.
            if ($stem !== [] && $options !== [] && (str_ends_with($line, '؟') || str_ends_with($line, '?'))) {
                $flush();
                $stem[] = $line;

                continue;
            }

            $option = self::option($line);

            if ($option !== null && $stem !== []) {
                $options[] = $option;

                continue;
            }

            // Text before the first option is the stem, including a stem that
            // wrapped over several lines.
            if ($options === []) {
                $stem[] = $line;
            }
        }

        $flush();

        return ['questions' => $questions];
    }

    private static function tidy(string $line): string
    {
        $line = trim($line);

        if ($line === '' || preg_match('/^(?:-{3,}|_{3,}|\*{3,}|—+)$/u', $line) === 1) {
            return '';
        }

        // Markdown decoration is not part of the question: a heading marker
        // ("## سؤال ۱") would otherwise end up inside the stem the student reads.
        $line = preg_replace('/^#{1,6}\s*/u', '', $line) ?? $line;

        // "1) …" is how the model numbers its questions; the number is not part
        // of the stem the student reads. Options are lettered, so this never
        // eats an option marker.
        $line = preg_replace('/^\s*[۰-۹0-9]{1,2}\s*[).\-–]\s*/u', '', $line) ?? $line;

        // Bold markers are formatting; the answer letter is read from the text.
        return trim(str_replace(['**', '__'], '', $line));
    }

    /** @return array{letter: string, text: string, checked: bool}|null */
    private static function option(string $line): ?array
    {
        $clean = preg_replace('/^[-•*]\s*/u', '', $line) ?? $line;

        if (preg_match('/^(الف|ب|ج|د|[a-dA-D])\s*[).\-–:]\s*(.+)$/u', $clean, $m) !== 1) {
            return null;
        }

        $checked = str_contains($m[2], '✓') || str_contains($m[2], '✔');
        $text = trim(str_replace(['✓', '✔'], '', $m[2]));

        return $text === '' ? null : ['letter' => $m[1], 'text' => $text, 'checked' => $checked];
    }

    private static function letter(string $text): ?string
    {
        $text = trim($text);

        foreach (self::LETTERS as $letter => $index) {
            if (preg_match('/^[*\s(]*'.preg_quote($letter, '/').'/u', $text) === 1) {
                return $letter;
            }
        }

        return null;
    }

    /**
     * @param  list<array{letter: string, text: string, checked: bool}>  $options
     * @return array<string, mixed>|null
     */
    private static function build(string $stem, array $options, ?string $answer, string $normalisedPassage): ?array
    {
        $stem = trim($stem);

        if ($stem === '' || count($options) !== 4) {
            return null;
        }

        $correct = null;

        if ($answer !== null && isset(self::LETTERS[$answer])) {
            $wanted = self::LETTERS[$answer];

            if (isset($options[$wanted]) && self::LETTERS[$options[$wanted]['letter']] === $wanted) {
                $correct = $wanted;
            }
        }

        if ($correct === null) {
            $checked = array_keys(array_filter($options, fn (array $o): bool => $o['checked']));

            if (count($checked) === 1) {
                $correct = $checked[0];
            }
        }

        if ($correct === null) {
            return null;
        }

        $quote = self::groundedQuote($stem, $normalisedPassage);

        if ($quote === null) {
            return null; // ungrounded: the book does not contain it, so neither do we
        }

        $built = [];

        foreach ($options as $index => $option) {
            $built[] = ['text' => $option['text'], 'is_correct' => $index === $correct];
        }

        return [
            'stem' => $stem,
            'difficulty' => 'Medium',
            'explanation' => 'گزینهٔ درست «'.$options[$correct]['text'].'» است؛ بر پایهٔ همین متن درس.',
            'source_quote' => $quote,
            'options' => $built,
        ];
    }

    /**
     * The longest prefix of the stem that occurs in the lesson's passage.
     *
     * Returned NORMALISED, which is the form the validator compares against —
     * so the quote is evidence of grounding, not decoration.
     */
    private static function groundedQuote(string $stem, string $normalisedPassage): ?string
    {
        if ($normalisedPassage === '') {
            return null;
        }

        $normalised = LessonQuestionValidator::normalise($stem);
        $words = preg_split('/\s+/u', trim($normalised)) ?: [];

        // A whole-stem prefix is too strict: a model often opens a question with
        // a scenario ("بیماری که ناپروکسن مصرف می‌کند…") that the book never
        // says verbatim, while still quoting its second half. The longest
        // run of consecutive words that really occurs in the passage is the
        // honest middle ground -- still the book's own words, just not its
        // opening ones.
        $count = count($words);

        for ($width = min(8, $count); $width >= 2; $width--) {
            for ($start = 0; $start + $width <= $count; $start++) {
                $candidate = implode(' ', array_slice($words, $start, $width));

                if (mb_strlen($candidate) >= self::MIN_QUOTE
                    && mb_strpos($normalisedPassage, $candidate) !== false) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
