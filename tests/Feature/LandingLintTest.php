<?php

namespace Tests\Feature;

use App\Support\LandingLint;
use Tests\TestCase;

/**
 * The linter is the gate an AI-written page must pass, so it is tested for
 * both directions: it must catch the specific mistakes a generator makes, and
 * it must not reject a page that actually follows the contract. A linter that
 * cries wolf gets switched off, which is worse than no linter.
 */
class LandingLintTest extends TestCase
{
    private function lint(string $blade): array
    {
        return (new LandingLint)->check($blade);
    }

    private function validPage(): string
    {
        return <<<'BLADE'
<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}" @include('partials.theme-attrs')>
<head>
    <meta charset="UTF-8">
    @include('partials.theme-vars')
</head>
<body>
    <h1 style="color:var(--c-primary)">{{ $tenant['name'] }}</h1>
    <a href="#courses">دوره‌ها</a>
</body>
</html>
BLADE;
    }

    // ------------------------------------------------------------ accepts good pages

    public function test_a_page_that_follows_the_contract_passes(): void
    {
        $this->assertSame([], $this->lint($this->validPage()));
    }

    public function test_json_ld_structured_data_is_not_treated_as_inline_script(): void
    {
        $page = str_replace(
            '</head>',
            '<script type="application/ld+json">{"@context":"https://schema.org"}</script></head>',
            $this->validPage(),
        );

        $this->assertSame([], $this->lint($page));
    }

    public function test_a_script_with_a_src_is_allowed(): void
    {
        $page = str_replace(
            '</body>',
            '<script src="{{ asset(\'landings/x/landing.js\') }}" defer></script></body>',
            $this->validPage(),
        );

        $this->assertSame([], $this->lint($page));
    }

    public function test_anchors_and_ids_are_not_mistaken_for_hex_colours(): void
    {
        $page = str_replace(
            '<body>',
            '<body><div id="abc12345" class="badge-fff"></div>',
            $this->validPage(),
        );

        $this->assertSame([], $this->lint($page));
    }

    public function test_comment_prose_about_colours_is_ignored(): void
    {
        $page = str_replace(
            '<body>',
            '<body>{{-- was #ff0000 before --}}<!-- legacy rgb(0,0,0) -->',
            $this->validPage(),
        );

        $this->assertSame([], $this->lint($page));
    }

    // ---------------------------------------------------------- rejects the mistakes

    public function test_a_missing_token_include_is_caught(): void
    {
        $page = str_replace("@include('partials.theme-vars')\n", '', $this->validPage());
        $problems = $this->lint($page);

        $this->assertNotEmpty($problems);
        $this->assertStringContainsString('partials.theme-vars', implode("\n", $problems));
    }

    public function test_a_duplicated_token_include_is_caught(): void
    {
        $page = str_replace(
            "@include('partials.theme-vars')",
            "@include('partials.theme-vars')\n@include('partials.theme-vars')",
            $this->validPage(),
        );

        $this->assertStringContainsString('exactly once', implode("\n", $this->lint($page)));
    }

    public function test_an_inline_script_is_caught(): void
    {
        $page = str_replace('</body>', "<script>alert('x')</script></body>", $this->validPage());

        $this->assertStringContainsString('Inline <script>', implode("\n", $this->lint($page)));
    }

    public function test_a_hard_coded_hex_colour_is_caught(): void
    {
        $page = str_replace('var(--c-primary)', '#0ea5e9', $this->validPage());
        $problems = implode("\n", $this->lint($page));

        $this->assertStringContainsString('#0ea5e9', $problems);
        $this->assertStringContainsString('Hard-coded colour', $problems);
    }

    public function test_short_and_alpha_hex_colours_are_caught(): void
    {
        foreach (['#fff', '#11223344'] as $hex) {
            $page = str_replace('var(--c-primary)', $hex, $this->validPage());
            $this->assertStringContainsString('Hard-coded colour', implode("\n", $this->lint($page)), $hex);
        }
    }

    public function test_a_hardcoded_rgb_colour_is_caught(): void
    {
        $page = str_replace('var(--c-primary)', 'rgb(14, 165, 233)', $this->validPage());

        $this->assertStringContainsString('Hard-coded colour', implode("\n", $this->lint($page)));
    }

    public function test_physical_side_properties_are_caught(): void
    {
        $page = str_replace(
            '<body>',
            '<body><div style="margin-left:8px;padding-right:4px"></div>',
            $this->validPage(),
        );
        $problems = implode("\n", $this->lint($page));

        $this->assertStringContainsString('margin-left', $problems);
        $this->assertStringContainsString('padding-right', $problems);
        $this->assertStringContainsString('inline-start', $problems);
    }

    public function test_an_inline_event_handler_is_caught(): void
    {
        $page = str_replace('<body>', '<body><button onclick="go()">x</button>', $this->validPage());

        $this->assertStringContainsString('Inline event handler', implode("\n", $this->lint($page)));
    }

    public function test_a_fragment_without_a_document_shell_is_caught(): void
    {
        $problems = implode("\n", $this->lint("@include('partials.theme-vars')<div>x</div>"));

        $this->assertStringContainsString('<!DOCTYPE html>', $problems);
        $this->assertStringContainsString('<html', $problems);
        $this->assertStringContainsString('<body', $problems);
    }

    public function test_every_problem_is_reported_not_just_the_first(): void
    {
        $page = str_replace(
            '</body>',
            '<div style="margin-left:1px;color:#ff0000"></div><script>x()</script></body>',
            $this->validPage(),
        );

        // margin-left + #ff0000 + inline script = 3 distinct findings.
        $this->assertGreaterThanOrEqual(3, count($this->lint($page)));
    }
}
