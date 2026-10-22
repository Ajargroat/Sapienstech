<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Pins the panel rail to the tenant token sheet.
 *
 * The sidebar is the product default, but it must stay one shared prototype:
 * no literal colour, no !important, one import, and the same JS contracts as
 * the topnav counterpart (the page-router keys on `.sidebar-link`, the
 * colour-scheme partial binds exactly one `#theme-toggle-btn`).
 */
class ShellNavDesignTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_rail_declares_no_literal_colour_and_no_override_flag(): void
    {
        $css = file_get_contents($this->root().'/resources/css/features/shell-nav.css');

        $this->assertNotFalse($css);

        foreach (['#', 'rgb(', 'hsl(', 'oklch(', '!important'] as $literal) {
            $this->assertStringNotContainsString($literal, $css, "shell-nav.css must not contain {$literal}");
        }

        foreach (['var(--c-primary)', 'var(--c-surface)', 'var(--radius-sidebar,', 'var(--sidebar-width,'] as $token) {
            $this->assertStringContainsString($token, $css, "shell-nav.css must consume {$token}");
        }
    }

    public function test_rail_is_imported_exactly_once_and_the_old_block_is_gone(): void
    {
        $app = file_get_contents($this->root().'/resources/css/app.css');

        $this->assertNotFalse($app);
        $this->assertSame(1, substr_count($app, '@import "./features/shell-nav.css"'));
        $this->assertStringNotContainsString('.consultant-sidebar {', $app);
        $this->assertStringNotContainsString('.sidebar-links {', $app);
        $this->assertStringNotContainsString('.sidebar-link {', $app);
    }

    public function test_each_sidebar_component_keeps_the_router_and_drawer_contracts(): void
    {
        foreach (['consultant', 'student', 'teacher'] as $role) {
            $view = file_get_contents($this->root()."/resources/views/components/{$role}/sidebar.blade.php");

            $this->assertNotFalse($view, $role);
            $this->assertStringContainsString('sidebar-link', $view, $role);
            $this->assertStringContainsString('data-sidebar-toggle', $view, $role);
            $this->assertStringContainsString('data-sidebar-scrim', $view, $role);
            $this->assertStringContainsString('aria-controls="app-sidebar"', $view, $role);
            $this->assertStringContainsString('id="app-sidebar"', $view, $role);
            $this->assertStringContainsString('topnav-icon-btn', $view, $role);
            if ($role !== 'teacher') {
                $this->assertStringContainsString('topnav-profile', $view, $role);
            }
        }
    }

    public function test_the_drawer_never_widens_the_page_it_sits_on(): void
    {
        $css = file_get_contents($this->root().'/resources/css/features/shell-nav.css');

        $this->assertNotFalse($css);
        $this->assertStringContainsString('@media (max-width: 900px)', $css);
        $this->assertStringContainsString('visibility: hidden;', $css);
        $this->assertStringContainsString('body.sidebar-open .consultant-sidebar', $css);
        $this->assertStringContainsString('body.sidebar-open .sidebar-toggle-bars', $css);
        $this->assertStringContainsString('.panel-heading', $css);
        $this->assertStringContainsString('.panel-heading-actions', $css);
        $this->assertStringContainsString('body[data-shell-nav="sidebar"] .panel-heading', $css);
        $this->assertStringContainsString('@media (max-width: 1100px)', $css);
        $this->assertStringContainsString('flex: 1 1 100%;', $css);
        $this->assertStringContainsString('.sidebar-account-actions .topnav-icon-btn {', $css);
        $this->assertStringContainsString('position: relative;', $css);
    }

    public function test_the_default_is_the_rail_and_the_topnav_remains_a_lever(): void
    {
        $theme = file_get_contents($this->root().'/config/theme.php');
        $studio = file_get_contents($this->root().'/config/studio.php');

        $this->assertNotFalse($theme);
        $this->assertNotFalse($studio);
        $this->assertMatchesRegularExpression("/'shell_nav' => 'sidebar'/", $theme);
        $this->assertStringContainsString("'in:sidebar,topnav'", $studio);
        $this->assertStringContainsString("'sidebar' => 'Sidebar'", $studio);
        $this->assertStringContainsString("'topnav' => 'Top bar'", $studio);
    }
}
