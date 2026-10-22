<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Pins the shared dashboard kit to the tenant token sheet.
 *
 * The three role dashboards are one prototype, not three bespoke skins. The kit
 * must stay colour-literal free (the only palette is `--c-*` / brand tokens),
 * must be imported once, and must be consumed by every dashboard view.
 */
class DashboardDesignTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_kit_declares_no_literal_colour_and_no_override_flag(): void
    {
        $css = file_get_contents($this->root().'/resources/css/features/dashboard.css');

        $this->assertNotFalse($css);

        foreach (['#', 'rgb(', 'hsl(', 'oklch(', '!important'] as $literal) {
            $this->assertStringNotContainsString($literal, $css, "dashboard.css must not contain {$literal}");
        }

        $this->assertStringContainsString('var(--c-primary)', $css);
        $this->assertStringContainsString('var(--c-surface)', $css);
        $this->assertStringContainsString('var(--radius-card)', $css);
    }

    public function test_kit_is_imported_exactly_once(): void
    {
        $app = file_get_contents($this->root().'/resources/css/app.css');

        $this->assertNotFalse($app);
        $this->assertSame(1, substr_count($app, '@import "./features/dashboard.css"'));
    }

    public function test_each_dashboard_consumes_the_shared_surfaces(): void
    {
        foreach (['consultant', 'student', 'teacher'] as $role) {
            $view = file_get_contents($this->root()."/resources/views/{$role}/dashboard.blade.php");

            $this->assertNotFalse($view, $role);
            $this->assertStringContainsString('dash-page', $view, $role);
            $this->assertStringContainsString('dash-stats', $view, $role);
            $this->assertStringContainsString('dash-layout', $view, $role);
            $this->assertStringContainsString('dash-panel', $view, $role);
        }
    }

    public function test_dashboard_views_do_not_carry_inline_colour_chrome(): void
    {
        foreach (['consultant', 'student', 'teacher'] as $role) {
            $view = file_get_contents($this->root()."/resources/views/{$role}/dashboard.blade.php");

            $this->assertDoesNotMatchRegularExpression(
                '/style="[^"]*(?:color|background|border)\s*:/i',
                $view,
                "{$role} dashboard must use classes for chrome, not inline colour styles",
            );
        }
    }

    public function test_dashboard_partials_exist_and_render_tokens(): void
    {
        foreach (['stat', 'ring', 'bars', 'quick-actions'] as $partial) {
            $path = $this->root()."/resources/views/partials/dashboard/{$partial}.blade.php";

            $this->assertFileExists($path, $partial);

            $source = file_get_contents($path);
            $this->assertStringContainsString('dash-', $source, $partial);
        }
    }
}
