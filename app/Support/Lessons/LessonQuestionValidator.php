<?php

namespace App\Support\Lessons;

/**
 * The gate between an AI answer and a student.
 *
 * A generated multiple-choice question is a correctness artifact, not copy, so
 * this validator REFUSES rather than repairs, and it refuses for a reason the
 * reviewer can read. Every rule below exists because its absence leaks a wrong
 * answer or an ungrounded one:
 *
 *   1. 3..10 questions (and never more than the tenant's ceiling);
 *   2. a stem of at least 10 characters;
 *   3. exactly four options, each with text, exactly ONE marked correct;
 *   4. a Persian explanation of at least 10 characters — a student who gets it
 *      wrong needs to read why the right answer is right;
 *   5. a difficulty from the enum the grader knows;
 *   6. `source_quote` must really occur in the lesson's own passage — a
 *      question about something the passage does not say is "beyond the
 *      passage" and is dropped.
 *
 * A question that fails any rule is dropped, reasons are collected, and if
 * NOTHING survives the whole draft is refused. Callers must treat
 * `payload === null` as "write no question anywhere".
 */
class LessonQuestionValidator
{
    public const DIFFICULTIES = ['Easy', 'Medium', 'Hard'];

    private const MIN_STEM = 10;

    private const MIN_EXPLANATION = 10;

    private const MIN_QUOTE = 8;

    /**
     * @param  array<string, mixed>  $decoded  the model's parsed JSON
     * @param  string  $passage  the lesson text the question must be grounded in
     * @return array{payload: ?array, errors: list<string>}
     */
    public static function validate(array $decoded, string $passage, int $max = 12): array
    {
        $errors = [];
        $questions = $decoded['questions'] ?? null;

        if (! is_array($questions) || $questions === []) {
            return ['payload' => null, 'errors' => ['پاسخ مدل هیچ سؤالی نداشت.']];
        }

        $max = max(1, $max);
        $normalisedPassage = self::normalise($passage);

        $kept = [];
        $seen = [];

        foreach ($questions as $index => $question) {
            if (! is_array($question)) {
                $errors[] = "question {$index}: not an object";

                continue;
            }

            $stem = trim((string) ($question['stem'] ?? ''));

            if (mb_strlen($stem) < self::MIN_STEM) {
                $errors[] = "question {$index}: stem too short";

                continue;
            }

            $fingerprint = self::normalise($stem);

            if (isset($seen[$fingerprint])) {
                $errors[] = "question {$index}: duplicate stem";

                continue;
            }

            $options = $question['options'] ?? null;

            if (! is_array($options) || count($options) !== 4) {
                $errors[] = "question {$index}: needs exactly four options";

                continue;
            }

            $optionTexts = [];
            $correctCount = 0;

            foreach ($options as $option) {
                if (! is_array($option) || trim((string) ($option['text'] ?? '')) === '') {
                    $errors[] = "question {$index}: an option has no text";
                    $optionTexts = [];

                    break;
                }

                if ((bool) ($option['is_correct'] ?? false)) {
                    $correctCount++;
                }

                $optionTexts[] = [
                    'text' => trim((string) $option['text']),
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                ];
            }

            if ($optionTexts === []) {
                continue;
            }

            if ($correctCount !== 1) {
                $errors[] = "question {$index}: exactly one option must be correct (got {$correctCount})";

                continue;
            }

            if (count(array_unique(array_map(fn ($o) => self::normalise($o['text']), $optionTexts))) !== 4) {
                $errors[] = "question {$index}: duplicate option text";

                continue;
            }

            $explanation = trim((string) ($question['explanation'] ?? ''));

            if (mb_strlen($explanation) < self::MIN_EXPLANATION) {
                $errors[] = "question {$index}: explanation too short";

                continue;
            }

            $difficulty = (string) ($question['difficulty'] ?? '');

            if (! in_array($difficulty, self::DIFFICULTIES, true)) {
                $errors[] = "question {$index}: difficulty not in ".implode('|', self::DIFFICULTIES);

                continue;
            }

            $quote = self::normalise((string) ($question['source_quote'] ?? ''));

            if (mb_strlen($quote) < self::MIN_QUOTE || ! str_contains($normalisedPassage, $quote)) {
                $errors[] = "question {$index}: source_quote is not in the passage";

                continue;
            }

            $seen[$fingerprint] = true;
            $kept[] = [
                'stem' => $stem,
                'options' => $optionTexts,
                'difficulty' => $difficulty,
                'source_quote' => (string) $question['source_quote'],
                'explanation' => $explanation,
            ];
        }

        if (count($kept) > $max) {
            $errors[] = 'too many questions: '.count($kept).' > '.$max;
            $kept = array_slice($kept, 0, $max);
        }

        if (count($kept) < 3) {
            return [
                'payload' => null,
                'errors' => $errors ?: ['fewer than three valid questions survived.'],
            ];
        }

        return ['payload' => ['questions' => $kept], 'errors' => $errors];
    }

    /**
     * Comparison form for quotes and stems: NFKC, Persian yeh/kaf unified,
     * harakat dropped, whitespace collapsed, Arabic-Indic and Persian digits
     * folded to ASCII so a quote the model retyped with ۱۲ still matches.
     */
    public static function normalise(string $text): string
    {
        // The `intl` extension is not loaded on this deployment, so NFC/NFKC
        // cannot be delegated to Normalizer. The extractor already normalised
        // the stored passage with python's unicodedata, and the equivalence
        // that actually matters for matching (yeh/kaf/digits) is done here.
        $map = [
            'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ];

        $text = strtr((string) $text, $map);
        // Presentation forms (U+FB50–FDFF, U+FE70–FEFF) become their base
        // letters, which is the only NFKC effect a model's re-typed quote needs.
        $text = strtr($text, self::presentationForms());
        $text = (string) preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $text);
        $text = (string) preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}]/u', ' ', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim(mb_strtolower($text));
    }

    /**
     * The common Arabic presentation forms, as a static map. Only the letters
     * that actually appear in a re-typed Persian quote are listed; anything
     * else passes through untouched, which is the safe direction (a quote that
     * fails to match is dropped, never merged with a different question).
     *
     * @return array<string, string>
     */
    private static function presentationForms(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $map = [];

        // [initial, medial, final, isolated] -> base letter
        $forms = [
            'ب' => ['\u{FE91}', '\u{FE92}', '\u{FE90}', '\u{FE8F}'],
            'پ' => ['\u{FB58}', '\u{FB59}', '\u{FB57}', '\u{FB56}'],
            'ت' => ['\u{FE97}', '\u{FE98}', '\u{FE96}', '\u{FE95}'],
            'ث' => ['\u{FE9B}', '\u{FE9C}', '\u{FE9A}', '\u{FE99}'],
            'ج' => ['\u{FE9F}', '\u{FEA0}', '\u{FE9E}', '\u{FE9D}'],
            'ح' => ['\u{FEA3}', '\u{FEA4}', '\u{FEA2}', '\u{FEA1}'],
            'خ' => ['\u{FEA7}', '\u{FEA8}', '\u{FEA6}', '\u{FEA5}'],
            'د' => ['', '', '\u{FEA9}', '\u{FEA9}'],
            'ذ' => ['', '', '\u{FEAB}', '\u{FEAB}'],
            'ر' => ['', '', '\u{FEAD}', '\u{FEAD}'],
            'ز' => ['', '', '\u{FEAF}', '\u{FEAF}'],
            'س' => ['\u{FEB3}', '\u{FEB4}', '\u{FEB2}', '\u{FEB1}'],
            'ش' => ['\u{FEB7}', '\u{FEB8}', '\u{FEB6}', '\u{FEB5}'],
            'ص' => ['\u{FEBB}', '\u{FEBC}', '\u{FEBA}', '\u{FEB9}'],
            'ض' => ['\u{FEBF}', '\u{FEC0}', '\u{FEBE}', '\u{FEBD}'],
            'ط' => ['\u{FEC3}', '\u{FEC4}', '\u{FEC2}', '\u{FEC1}'],
            'ظ' => ['\u{FEC7}', '\u{FEC8}', '\u{FEC6}', '\u{FEC5}'],
            'ع' => ['\u{FECB}', '\u{FECC}', '\u{FECA}', '\u{FEC9}'],
            'غ' => ['\u{FECF}', '\u{FED0}', '\u{FECE}', '\u{FECD}'],
            'ف' => ['\u{FED3}', '\u{FED4}', '\u{FED2}', '\u{FED1}'],
            'ق' => ['\u{FED7}', '\u{FED8}', '\u{FED6}', '\u{FED5}'],
            'ک' => ['\u{FEDB}', '\u{FEDC}', '\u{FEDA}', '\u{FED9}'],
            'ل' => ['\u{FEDF}', '\u{FEE0}', '\u{FEDE}', '\u{FEDD}'],
            'م' => ['\u{FEE3}', '\u{FEE4}', '\u{FEE2}', '\u{FEE1}'],
            'ن' => ['\u{FEE7}', '\u{FEE8}', '\u{FEE6}', '\u{FEE5}'],
            'ه' => ['\u{FEEB}', '\u{FEEC}', '\u{FEEA}', '\u{FEE9}'],
            'ی' => ['\u{FEF3}', '\u{FEF4}', '\u{FEF2}', '\u{FEF1}'],
        ];

        foreach ($forms as $base => $glyphs) {
            foreach ($glyphs as $glyph) {
                if ($glyph === '') {
                    continue;
                }

                $map[preg_replace_callback('/\\\\u\{([0-9A-Fa-f]+)\}/', fn ($m) => mb_chr(hexdec($m[1]), 'UTF-8'), $glyph)] = $base;
            }
        }

        return $map;
    }
}
