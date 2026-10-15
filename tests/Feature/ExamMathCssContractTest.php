<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * MathJax placement contract for the exam text containers.
 *
 * Tailwind's preflight resets every <svg> to `display:block`. MathJax wraps an
 * inline `$…$` formula in an inline <mjx-container> whose only child is an
 * <svg>, so that reset put a block box inside an inline box and every formula
 * dropped onto its own centered line — measured against the real build, a
 * three-line question body was 255px tall instead of ~90px, and the picker
 * showed one short line 114px tall instead of 28px. The existing
 * `direction:ltr` + `unicode-bidi:isolate` rules never touched `display`, so
 * they could not prevent it.
 *
 * This pins the one declaration that fixes it, on every container the five
 * exam surfaces use (picker, preview, runner, review, result).
 */
class ExamMathCssContractTest extends TestCase
{
    /** @var array<int, string> */
    private const CONTAINERS = ['.quiz-text', '.opt-text', '.picker-q-text', '.picker-options'];

    public function test_the_mathjax_svg_is_forced_back_to_display_inline(): void
    {
        $css = file_get_contents(resource_path('css/features/exams.css'));
        $this->assertIsString($css);

        preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $rules, PREG_SET_ORDER);

        $selectors = [];
        $body = null;

        foreach ($rules as $rule) {
            if (str_contains($rule[1], 'mjx-container svg')) {
                $selectors[] = $rule[1];
                $body = $rule[2];
            }
        }

        $this->assertNotEmpty($selectors, 'exams.css must contain a rule targeting the MathJax <svg>.');

        $all = implode("\n", $selectors);

        foreach (self::CONTAINERS as $container) {
            $this->assertStringContainsString(
                $container.' mjx-container svg',
                $all,
                "The {$container} MathJax rule must be scoped by the svg selector."
            );
        }

        $this->assertStringContainsString(
            'display: inline',
            (string) $body,
            'The MathJax svg rule must set `display: inline`, or Tailwind preflight (svg{display:block}) '
            .'breaks every formula onto its own line.'
        );
    }

    public function test_the_mathjax_container_keeps_the_rtl_isolation_rules(): void
    {
        $css = file_get_contents(resource_path('css/features/exams.css'));
        $this->assertIsString($css);

        preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $rules, PREG_SET_ORDER);

        foreach ($rules as $rule) {
            if (! str_contains($rule[1], 'mjx-container') || str_contains($rule[1], 'mjx-container svg')) {
                continue;
            }

            $this->assertStringContainsString('unicode-bidi: isolate', $rule[2]);
            $this->assertStringContainsString('direction: ltr', $rule[2]);

            return;
        }

        $this->fail('exams.css must keep the mjx-container direction/isolate rule.');
    }
}
