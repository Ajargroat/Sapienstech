<?php

namespace Tests\Feature\Student;

use Tests\TestCase;

/**
 * Regression guard for the real cause of 'the student section just errors'.
 *
 * student-dashboard.js read meta[name=csrf-token] to build its X-CSRF-TOKEN
 * header. No student layout renders that meta tag, so the header went out
 * empty and Laravel's VerifyCsrfToken answered 419 on every done-toggle and
 * every comment -- which the script then swallowed into a silent revert.
 *
 * The contract is therefore: the page must hand the script a token.
 */
class StudentScheduleCsrfTest extends TestCase
{
    public function test_the_schedule_dialog_exposes_a_csrf_token(): void
    {
        $blade = file_get_contents(resource_path('views/student/dashboard.blade.php'));

        $this->assertStringContainsString(
            'id="schedule-detail-modal"',
            $blade,
            'the portal dialog must exist -- it carries the endpoints and the token.'
        );

        $this->assertMatchesRegularExpression(
            '/id="schedule-detail-modal"[\s\S]{0,600}?data-csrf="\{\{ csrf_token\(\) \}\}"/',
            $blade,
            'the dialog must carry data-csrf (no student layout renders a csrf meta tag).'
        );
    }

    public function test_no_student_layout_claims_to_render_a_csrf_meta_tag(): void
    {
        // The old script depended on this meta tag. If a layout ever gains one
        // that is fine, but the script must not REQUIRE it -- so assert the
        // dependency the other way round: the script reads the dataset first.
        $script = file_get_contents(resource_path('js/features/student-dashboard.js'));

        $this->assertStringContainsString('root?.dataset.csrf', $script,
            'the portal script must read the token from the dialog it was given.');
    }
}
