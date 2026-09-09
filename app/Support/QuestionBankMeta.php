<?php

namespace App\Support;

/**
 * Canonicalizes the raw OCR'd metadata on Supabase bank rows into the small
 * set of fields the local picker filters and tags on: a Persian lesson name
 * out of the five allowed ones, a corporate name merged across its spelling
 * variants (قلمچی/قلم‌چی, ماراثون/مارآتون/…), and a human chapter label.
 *
 * One source of truth for the importer, the backfill command and the exams
 * controller, so the same string written into questions.corp is the one the
 * filter dropdown and the card tag compare against.
 */
class QuestionBankMeta
{
    /** Supabase subject codes → the five consultant-selectable lessons. */
    private const SUBJECTS = [
        'zist' => 'زیست‌شناسی',
        'biol' => 'زیست‌شناسی',
        'biology' => 'زیست‌شناسی',
        'shimi' => 'شیمی',
        'chem' => 'شیمی',
        'chemistry' => 'شیمی',
        'physic' => 'فیزیک',
        'physics' => 'فیزیک',
        'fizik' => 'فیزیک',
        'math' => 'ریاضی',
        'maths' => 'ریاضی',
        'riazi' => 'ریاضی',
        'zamin' => 'زمین‌شناسی',
        'earth' => 'زمین‌شناسی',
        'geo' => 'زمین‌شناسی',
        'زمین' => 'زمین‌شناسی',
        'زمین شناسی' => 'زمین‌شناسی',
    ];

    /**
     * Spelling-variant keys (see corpKey) → the display name to merge them
     * into. Canons follow the exam_companies spellings where one exists.
     */
    private const CORPS = [
        'ماز' => 'ماز',
        'گاج' => 'گاج',
        'سنجش' => 'سنجش',
        'پورسینا' => 'پورسینا',
        'دیار' => 'دیار',
        'دیاز' => 'دیاز',
        'دیاژ' => 'دیاز',
        'قلمچی' => 'قلم‌چی',
        'خیلیسبز' => 'خیلی‌سبز',
        'گزینهدو' => 'گزینه‌دو',
        'ماراتون' => 'ماراتون',
        'ماراثون' => 'ماراتون',
        'ماراتیون' => 'ماراتون',
        'مارناتون' => 'ماراتون',
        'مارایتون' => 'ماراتون',
    ];

    /** Grades seen in the bank; anything else contributes no label part. */
    private const GRADES = ['10' => 'دهم', '11' => 'یازدهم', '12' => 'دوازدهم'];

    /** Map a raw subject code (case-insensitive) onto a canonical lesson. */
    public static function subject(?string $code): ?string
    {
        $key = mb_strtolower(trim((string) $code), 'UTF-8');

        return self::SUBJECTS[$key] ?? null;
    }

    /**
     * Merge OCR spelling variants of a company name into one canonical
     * spelling. Unknown names survive trimmed but untouched.
     */
    public static function corp(?string $raw): ?string
    {
        $trimmed = trim(preg_replace('/\s+/u', ' ', (string) $raw));

        if ($trimmed === '') {
            return null;
        }

        return self::CORPS[self::corpKey($trimmed)] ?? $trimmed;
    }

    /** "chapter4" + grade 10 → "فصل 4 · دهم"; anything unparseable → null. */
    public static function chapterLabel(?string $topic, int|string|null $grade): ?string
    {
        $topic = trim((string) $topic);

        if (! preg_match('/^chapter([0-9]{1,2})$/i', $topic, $m)) {
            return null;
        }

        // ASCII digits are stored; the view renders them via persian_digits().
        $label = 'فصل '.(int) $m[1];
        $gradeName = self::GRADES[trim((string) $grade)] ?? null;

        return $gradeName ? $label.' · '.$gradeName : $label;
    }

    /**
     * A spelling-insensitive key: Arabic letter forms folded to Persian,
     * alef variants unified, spaces and zero-width joiners stripped.
     */
    private static function corpKey(string $value): string
    {
        $folded = strtr($value, [
            'ي' => 'ی', 'ك' => 'ک', 'ى' => 'ی',
            'آ' => 'ا', 'أ' => 'ا', 'إ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ؤ' => 'و', 'ء' => '',
        ]);

        return preg_replace('/[\x{200b}-\x{200f}\x{202a}-\x{202e}\x{200c}\x{200d}\s]/u', '', $folded) ?? $value;
    }
}
