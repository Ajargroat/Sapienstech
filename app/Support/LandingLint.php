<?php

namespace App\Support;

/**
 * Static validation of a bespoke landing page against the page contract.
 *
 * This is the "validate, don't trust" step of the AI workflow. A generated
 * page is HTML written by a model, and the things that make such a page
 * dangerous or off-brand are all statically detectable:
 *
 *   - an inline <script>, which the CSP forbids and which turns a tenant's
 *     landing page into arbitrary same-origin code;
 *   - a hard-coded colour, which is brand drift the moment the tenant changes
 *     palette;
 *   - a missing token include, which renders the page unbranded;
 *   - a physical margin/padding side, which breaks in RTL.
 *
 * Every check returns a plain message list. Nothing here throws on a bad page:
 * a linter that crashes tells a model nothing it can act on.
 */
class LandingLint
{
    /** @return list<string> human-readable violations */
    public function check(string $blade): array
    {
        $problems = [];

        $problems = array_merge($problems, $this->checkIncludes($blade));
        $problems = array_merge($problems, $this->checkNoInlineScript($blade));
        $problems = array_merge($problems, $this->checkNoHardcodedColours($blade));
        $problems = array_merge($problems, $this->checkLogicalProperties($blade));
        $problems = array_merge($problems, $this->checkNoInlineHandlers($blade));
        $problems = array_merge($problems, $this->checkDocumentShell($blade));

        return $problems;
    }

    /** The two partials that carry the whole brand. */
    private function checkIncludes(string $blade): array
    {
        $problems = [];

        foreach (['partials.theme-vars', 'partials.theme-attrs'] as $partial) {
            $count = substr_count($blade, "@include('{$partial}'");

            if ($count === 1) {
                continue;
            }

            $problems[] = $count === 0
                ? "Missing required include: @include('{$partial}'). The page would render unbranded."
                : "@include('{$partial}') appears {$count} times; it must appear exactly once.";
        }

        return $problems;
    }

    /**
     * No inline <script>. A tag with a src= is a normal asset and is fine;
     * anything else runs as same-origin script and the CSP blocks it anyway —
     * catching it here says so while the page is still being written.
     */
    private function checkNoInlineScript(string $blade): array
    {
        if (! preg_match_all('/<script\b([^>]*)>/i', $blade, $matches)) {
            return [];
        }

        $problems = [];

        foreach ($matches[1] as $attrs) {
            if (preg_match('/\bsrc\s*=/i', $attrs) === 1) {
                continue;
            }

            // A src-less <script type="application/ld+json"> is structured
            // data, not executable code, and is allowed.
            if (preg_match('/type\s*=\s*["\']application\/(ld\+json|json)["\']/i', $attrs) === 1) {
                continue;
            }

            $problems[] = 'Inline <script> is not allowed (CSP blocks it, and a page must not carry executable code inline). Put behaviour in landing.js and load it with a src=.';
        }

        return $problems;
    }

    /**
     * No raw hex colours. A literal colour is the clearest sign of brand drift:
     * it survives every future palette change and quietly stops matching.
     *
     * Deliberately narrow, to stay useful rather than noisy:
     *   - bare hex only where a colour value is expected (README prose and
     *     comments are stripped first);
     *   - `#id` selectors and URL fragments are not colours;
     *   - rgb()/hsl() literals are caught too, since they are the same mistake
     *     in a different notation.
     */
    private function checkNoHardcodedColours(string $blade): array
    {
        $code = $this->stripComments($blade);

        $problems = [];

        // #rgb, #rrggbb, #rrggbbaa — but not "#section" or "#item-3".
        if (preg_match_all('/(?<![\w&-])#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})\b/i', $code, $m)) {
            foreach (array_unique($m[0]) as $hex) {
                $problems[] = "Hard-coded colour {$hex}: use a token from the core sheet (e.g. var(--c-primary)) instead of a literal.";
            }
        }

        if (preg_match_all('/\b(?:rgba?|hsla?)\(\s*[\d.]+/i', $code, $m)) {
            foreach (array_unique($m[0]) as $fn) {
                $problems[] = 'Hard-coded colour in '.trim($fn).'(…): use a token, or color-mix() over a token, instead of a literal.';
            }
        }

        return $problems;
    }

    /**
     * Physical side properties break RTL. The platform is Persian-first and
     * every tenant declares its own direction, so a page that only works
     * left-to-right is broken for most of them.
     */
    private function checkLogicalProperties(string $blade): array
    {
        $code = $this->stripComments($blade);

        if (! preg_match_all('/\b(margin|padding|border)-(left|right)\s*:/i', $code, $m, PREG_SET_ORDER)) {
            return [];
        }

        $problems = [];

        foreach ($m as $hit) {
            $problems[] = "Physical property {$hit[1]}-{$hit[2]} breaks in RTL: use {$hit[1]}-inline-start / {$hit[1]}-inline-end.";
        }

        return array_values(array_unique($problems));
    }

    /** on*="…" handlers are inline script wearing a different hat. */
    private function checkNoInlineHandlers(string $blade): array
    {
        if (! preg_match_all('/\son(?:click|load|error|change|submit|input|focus|blur|mouse\w+|key\w+)\s*=/i', $blade, $m)) {
            return [];
        }

        return ['Inline event handler ('.trim($m[0][0]).') — the CSP forbids it. Attach listeners from landing.js instead.'];
    }

    /**
     * The page must be a complete document: Blade partials cannot supply
     * <html>/<head>/<body>, and a page missing them renders as unstyled text.
     */
    private function checkDocumentShell(string $blade): array
    {
        $problems = [];

        foreach (['<!DOCTYPE html>', '<html', '<head', '<body'] as $tag) {
            if (stripos($blade, $tag) === false) {
                $problems[] = "Missing {$tag} — a bespoke landing page is a complete document, not a fragment.";
            }
        }

        return $problems;
    }

    /** Remove Blade and HTML comments so prose about colours is not flagged. */
    private function stripComments(string $blade): string
    {
        $blade = preg_replace('/\{\{--.*?--\}\}/s', '', $blade) ?? $blade;

        return preg_replace('/<!--.*?-->/s', '', $blade) ?? $blade;
    }
}
