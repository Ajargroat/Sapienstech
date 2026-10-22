<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Pins the shared app surface to the tenant token sheet.
 *
 * The dashboards keep their `dash-*` kit. Every other authenticated screen opts
 * into the same visual language with one `.page-surface` root, which means the
 * sheet is token-only and every selector is scoped to that marker. The schedule
 * screens, the exam runner and the lesson reader are deliberately outside it:
 * their own contracts are pinned here so a later sweep cannot absorb them by
 * accident.
 */
class AppSurfaceDesignTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_surface_css_is_token_only_and_has_no_literal_colour(): void
    {
        $css = file_get_contents($this->root().'/resources/css/features/app-surface.css');

        $this->assertNotFalse($css);

        foreach (['#', 'rgb(', 'rgba(', 'hsl(', 'oklch(', '!important'] as $literal) {
            $this->assertStringNotContainsString($literal, $css, "app-surface.css must not contain {$literal}");
        }

        foreach (['var(--c-primary)', 'var(--c-surface)', 'var(--c-border)', 'var(--radius-card)'] as $token) {
            $this->assertStringContainsString($token, $css, "app-surface.css must consume {$token}");
        }
    }

    public function test_every_selector_is_scoped_to_the_page_surface_marker(): void
    {
        $css = file_get_contents($this->root().'/resources/css/features/app-surface.css');
        $this->assertNotFalse($css);

        $css = preg_replace('!/\*.*?\*/!s', '', $css);
        $this->assertNotNull($css);

        preg_match_all('/(?:^|\n)\s*([^@{}\n][^{}]*)\{/', $css, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $selector) {
            $selector = trim($selector);

            if ($selector === '' || str_starts_with($selector, '@')) {
                continue;
            }

            $this->assertStringStartsWith(
                '.page-surface',
                $selector,
                "Every app-surface selector must start with .page-surface; got: {$selector}"
            );
        }
    }

    public function test_surface_css_is_imported_once_after_the_dashboard_kit(): void
    {
        $app = file_get_contents($this->root().'/resources/css/app.css');
        $this->assertNotFalse($app);

        $this->assertSame(1, substr_count($app, '@import "./features/app-surface.css"'));
        $this->assertGreaterThan(
            strpos($app, '@import "./features/dashboard.css"'),
            strpos($app, '@import "./features/app-surface.css"')
        );
    }

    public function test_opted_in_screens_carry_the_marker(): void
    {
        $views = [
            'resources/views/consultant/deals.blade.php',
            'resources/views/consultant/notifications.blade.php',
            'resources/views/consultant/requests.blade.php',
            'resources/views/consultant/blog/index.blade.php',
            'resources/views/consultant/blog/form-page.blade.php',
            'resources/views/consultant/bulk/exams.blade.php',
            'resources/views/consultant/bulk/history.blade.php',
            'resources/views/consultant/chat/index.blade.php',
            'resources/views/consultant/consultations/index.blade.php',
            'resources/views/consultant/consultations/show.blade.php',
            'resources/views/consultant/settings/layout.blade.php',
            'resources/views/consultant/students/create.blade.php',
            'resources/views/consultant/students/profile.blade.php',
            'resources/views/consultant/students/report-card.blade.php',
            'resources/views/consultant/students/request.blade.php',
            'resources/views/consultant/students/exams.blade.php',
            'resources/views/consultant/students/exam-result.blade.php',
            'resources/views/consultant/students/exam-review.blade.php',
            'resources/views/student/deals.blade.php',
            'resources/views/student/exam-result.blade.php',
            'resources/views/student/report-card.blade.php',
            'resources/views/student/lessons.blade.php',
            'resources/views/student/notifications.blade.php',
            'resources/views/student/assignments/index.blade.php',
            'resources/views/student/assignments/show.blade.php',
            'resources/views/student/settings/layout.blade.php',
            'resources/views/student/chat/index.blade.php',
            'resources/views/teacher/materials.blade.php',
            'resources/views/teacher/assignments/index.blade.php',
            'resources/views/teacher/assignments/show.blade.php',
            'resources/views/teacher/students/index.blade.php',
            'resources/views/teacher/students/show.blade.php',
        ];

        foreach ($views as $view) {
            $html = file_get_contents($this->root().'/'.$view);

            $this->assertNotFalse($html, $view);
            $this->assertStringContainsString('page-surface', $html, $view);
        }
    }

    public function test_chat_workspaces_use_the_shared_surface_and_shell(): void
    {
        foreach (['consultant', 'student'] as $portal) {
            $view = file_get_contents($this->root()."/resources/views/{$portal}/chat/index.blade.php");
            $this->assertNotFalse($view);
            $this->assertStringContainsString('page-surface', $view);
            $this->assertStringContainsString('partials.chat.workspace', $view);
        }

        $workspace = file_get_contents($this->root().'/resources/views/partials/chat/workspace.blade.php');
        $this->assertNotFalse($workspace);
        $this->assertStringContainsString('data-chat-root', $workspace);

        $css = file_get_contents($this->root().'/resources/css/features/chat.css');
        $this->assertNotFalse($css);
        $this->assertStringContainsString('var(--c-primary)', $css);
    }

    public function test_immersive_and_schedule_screens_are_outside_the_surface(): void
    {
        $excluded = [
            'resources/views/consultant/dashboard.blade.php',
            'resources/views/consultant/students/schedule.blade.php',
            'resources/views/consultant/bulk/schedule.blade.php',
            'resources/views/student/dashboard.blade.php',
            'resources/views/student/timetable.blade.php',
            'resources/views/student/exam-runner.blade.php',
            'resources/views/student/lesson-reader.blade.php',
            'resources/views/teacher/dashboard.blade.php',
            'resources/views/teacher/schedule.blade.php',
        ];

        foreach ($excluded as $view) {
            $html = file_get_contents($this->root().'/'.$view);

            $this->assertNotFalse($html, $view);
            $this->assertStringNotContainsString('page-surface', $html, $view);
        }
    }

    public function test_page_heads_leave_room_for_their_filter_popovers(): void
    {
        $css = file_get_contents($this->root().'/resources/css/features/app-surface.css');

        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression(
            '/\.page-surface \.panel-heading,\s*\.page-surface \.settings-head\s*\{[^}]*overflow:\s*visible;/s',
            $css,
            'The exams/report-card filter popover is inside .panel-heading and must not be clipped.'
        );
    }

    public function test_the_schedule_system_keeps_its_own_contracts(): void
    {
        $contracts = [
            'resources/views/consultant/students/schedule.blade.php' => ['id="schedule-app"', 'data-url-items'],
            'resources/views/consultant/bulk/schedule.blade.php' => ['@section(\'bulk-content\')'],
            'resources/views/teacher/schedule.blade.php' => ['data-schedule-grid', 'data-schedule-dialog'],
            'resources/views/student/timetable.blade.php' => ['teacher-class-row', 'student-side'],
            'resources/views/student/exam-runner.blade.php' => ['id="exam-runner"', 'id="runner-timer"'],
            'resources/views/student/lesson-reader.blade.php' => ['data-lesson-reader', 'data-progress-url'],
        ];

        foreach ($contracts as $view => $needles) {
            $html = file_get_contents($this->root().'/'.$view);
            $this->assertNotFalse($html, $view);

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $html, "{$view} must keep {$needle}");
            }
        }
    }
}
