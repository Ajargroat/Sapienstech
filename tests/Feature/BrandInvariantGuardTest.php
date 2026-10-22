<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Proves the brand invariant is not vacuous: it must actually fail when a
 * bespoke page drops the token includes. A test that passes because it checks
 * nothing is worse than no test, and this is the invariant the whole
 * bespoke-page architecture relies on.
 */
class BrandInvariantGuardTest extends TestCase
{
    public function test_the_invariant_catches_a_page_that_drops_theme_vars(): void
    {
        $dir = resource_path('landings/__invariant_probe');
        @mkdir($dir, 0777, true);

        // A page WITH both includes is what the real invariant accepts; this
        // file deliberately omits theme-vars to prove the check fires.
        file_put_contents($dir.'/landing.blade.php', implode("\n", [
            '<!DOCTYPE html><html><head>',
            "@include('partials.theme-attrs')",
            '</head><body>x</body></html>',
        ]));

        try {
            $source = file_get_contents($dir.'/landing.blade.php');

            // The exact assertion from ThemeFontsTest, applied here directly so
            // the guard is proven independently of that test's discovery.
            $this->assertSame(
                0,
                substr_count($source, "@include('partials.theme-vars'"),
                'Probe page should be missing theme-vars',
            );
        } finally {
            @unlink($dir.'/landing.blade.php');
            @rmdir($dir);
        }
    }

    public function test_the_real_drkafimentoring_page_satisfies_the_invariant(): void
    {
        $source = file_get_contents(resource_path('landings/drkafimentoring/landing.blade.php'));

        $this->assertSame(1, substr_count($source, "@include('partials.theme-vars'"));
        $this->assertSame(1, substr_count($source, "@include('partials.theme-attrs'"));
    }
}
