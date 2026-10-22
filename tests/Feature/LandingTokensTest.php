<?php

namespace Tests\Feature;

use App\Support\LandingTokens;
use App\Support\ThemeTokens;
use Tests\TestCase;

/**
 * The core token sheet is a curated subset of ThemeTokens, and this file is
 * what keeps it honest: every token a bespoke page is told to use must
 * actually be emitted by the shared token pipeline, or the page renders an
 * undefined variable and nobody finds out until a tenant looks at it.
 */
class LandingTokensTest extends TestCase
{
    public function test_every_core_token_exists_in_the_full_sheet(): void
    {
        // A core path that ThemeTokens does not map would round-trip to an
        // undefined CSS variable: --c-nope: ; and an invisible bug.
        $this->assertSame([], LandingTokens::unknownPaths());
    }

    public function test_the_core_sheet_is_much_smaller_than_the_full_sheet(): void
    {
        // The whole point. If this creeps back toward the full sheet, the
        // bespoke pages are being handed the skeleton again.
        $this->assertLessThan(
            count(ThemeTokens::VARS) / 3,
            count(LandingTokens::names()),
            'The core sheet has grown back into a layout sheet.',
        );
    }

    public function test_the_core_sheet_covers_the_six_brand_groups(): void
    {
        $groups = array_keys(LandingTokens::groups());

        sort($groups);

        $this->assertSame(['color', 'motion', 'rhythm', 'shape', 'state', 'type'], $groups);
    }

    public function test_no_core_token_is_a_layout_geometry_lever(): void
    {
        // Layout tokens describe the platform's skeleton. A bespoke page that
        // reads them is being told how to lay itself out, which is the exact
        // matrix this architecture exists to escape.
        $banned = ['sidebar-width', 'sidebar-top', 'sidebar-bottom', 'sidebar-offset',
            'topnav-height', 'hero-cols', 'hero-min-height', 'nav-height'];

        foreach (LandingTokens::names() as $name) {
            $this->assertNotContains($name, $banned);
        }
    }

    public function test_the_resolved_sheet_returns_real_values(): void
    {
        $resolved = LandingTokens::resolved();

        $this->assertNotEmpty($resolved);
        $this->assertArrayHasKey('c-primary', $resolved);

        foreach ($resolved as $name => $value) {
            $this->assertNotSame('', $value, $name);
        }
    }
}
