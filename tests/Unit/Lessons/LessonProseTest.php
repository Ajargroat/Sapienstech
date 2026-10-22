<?php

namespace Tests\Unit\Lessons;

use App\Support\Lessons\LessonProse;
use PHPUnit\Framework\TestCase;

/**
 * The prose reader, pinned against the two shapes the configured model really
 * produced. Both are copied from stored drafts, not invented: a lenient parser
 * with a made-up example proves nothing.
 */
class LessonProseTest extends TestCase
{
    private const PASSAGE = 'آدنوزین در کلیه از طریق گیرنده‌های A1 در نواحی پری‌گلومرولی جریان خون کلیه را کم می‌کند. '
        .'مصرف ناپروکسن همراه با والسارتان و اسپیرونولاکتون خطر هیپرکالمی و آریتمی قلبی را بالا می‌برد. '
        .'دیورتیک‌های مهارکننده کربنیک‌انیدراز در بیماران سیروتیک باعث اسیدوز متابولیک هیپرکلرمیک می‌شوند.';

    /** Shape A: "سؤال ۱: …" with bulleted options and a ✓ on the answer. */
    public function test_it_reads_the_checkmark_shape(): void
    {
        $raw = <<<'TXT'
        سؤال ۱: آدنوزین در کلیه از طریق گیرنده‌های A1 در نواحی پری‌گلومرولی چه اثری دارد؟
        - الف) افزایش جریان خون کلیه و افزایش GFR
        - ب) کاهش جریان خون کلیه و کاهش GFR ✓
        - ج) افزایش دفع پتاسیم از توبول‌ها
        - د) مهار بازجذب سدیم

        TXT;

        $parsed = LessonProse::parse($raw, self::PASSAGE);

        $this->assertCount(1, $parsed['questions']);

        $question = $parsed['questions'][0];
        $this->assertCount(4, $question['options']);
        $this->assertSame('Medium', $question['difficulty']);
        $this->assertSame(
            ['کاهش جریان خون کلیه و کاهش GFR'],
            array_column(array_filter($question['options'], fn ($o) => $o['is_correct']), 'text')
        );
        // strip of decoration is part of reading it
        $this->assertStringNotContainsString('✓', $question['options'][1]['text']);
    }

    /** Shape B: "**سؤال ۱**", the stem on the next line, and "پاسخ صحیح: ب". */
    public function test_it_reads_the_answer_line_shape(): void
    {
        $raw = <<<'TXT'
        سلام! سه سؤال طراحی کردم:

        ---

        **سؤال ۱**
        مصرف ناپروکسن در بیماری که والسارتان و اسپیرونولاکتون دریافت می‌کند، با کدام خطر همراه است؟

        الف) هیپوکالمی شدید
        ب) هیپرکالمی و آریتمی قلبی
        ج) اسیدوز متابولیک هیپرکلرمیک
        د) افزایش شدید GFR

        پاسخ صحیح: **ب**

        TXT;

        $parsed = LessonProse::parse($raw, self::PASSAGE);

        $this->assertCount(1, $parsed['questions']);

        $question = $parsed['questions'][0];
        $this->assertStringStartsWith('مصرف ناپروکسن', $question['stem']);
        $this->assertStringNotContainsString('**', $question['stem']);
        $this->assertSame('هیپرکالمی و آریتمی قلبی', $question['options'][1]['text']);
        $this->assertTrue($question['options'][1]['is_correct']);
    }

    /** A question the book does not support is dropped, not guessed at. */
    public function test_it_drops_a_question_with_no_grounding_in_the_passage(): void
    {
        $raw = <<<'TXT'
        سؤال ۱: کدام قارچ سمی در جنگل‌های شمال ایران بیشتر دیده می‌شود؟
        - الف) آمانیتا
        - ب) بولتوس
        - ج) تروفلا
        - د) مورل ✓
        TXT;

        $this->assertSame([], LessonProse::parse($raw, self::PASSAGE)['questions']);
    }

    /** Three options is not a four-option question; the shape is never guessed. */
    public function test_it_drops_an_incomplete_option_set(): void
    {
        $raw = <<<'TXT'
        سؤال ۱: آدنوزین در کلیه از طریق گیرنده‌های A1 چه اثری دارد؟
        - الف) افزایش جریان خون
        - ب) کاهش جریان خون ✓
        - ج) بی‌اثر است
        TXT;

        $this->assertSame([], LessonProse::parse($raw, self::PASSAGE)['questions']);
    }

    /** Two check marks is ambiguous, so the question is refused. */
    public function test_it_drops_a_question_with_two_marked_answers(): void
    {
        $raw = <<<'TXT'
        سؤال ۱: آدنوزین در کلیه از طریق گیرنده‌های A1 چه اثری دارد؟
        - الف) افزایش جریان خون ✓
        - ب) کاهش جریان خون ✓
        - ج) افزایش دفع پتاسیم از توبول‌ها
        - د) مهار بازجذب سدیم
        TXT;

        $this->assertSame([], LessonProse::parse($raw, self::PASSAGE)['questions']);
    }

    /** Serial-numbered stems ("1) …") must not be mistaken for options. */
    public function test_a_numbered_stem_is_a_question_not_an_option(): void
    {
        $raw = <<<'TXT'
        1) آدنوزین در کلیه از طریق گیرنده‌های A1 چه اثری دارد؟
        - الف) افزایش جریان خون
        - ب) کاهش جریان خون کلیه و کاهش GFR ✓
        - ج) افزایش دفع پتاسیم
        - د) مهار بازجذب سدیم
        TXT;

        $parsed = LessonProse::parse($raw, self::PASSAGE);

        $this->assertCount(1, $parsed['questions']);
        $this->assertStringStartsWith('آدنوزین', $parsed['questions'][0]['stem']);
        $this->assertCount(4, $parsed['questions'][0]['options']);
    }
}
