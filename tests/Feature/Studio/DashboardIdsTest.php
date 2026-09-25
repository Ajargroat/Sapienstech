<?php

namespace Tests\Feature\Studio;

use App\Models\Domain;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ConfigWriter;
use App\Support\StudioStyles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 1b: the dashboard id surface.
 *
 * The landing page's ids resolve against config/studio.php; the three role
 * dashboards do not have schema fields (declaring dead levers would violate
 * the config's own "nothing consumes them yet" rule). Their ids are
 * TEMPLATE-OWNED: the markup emits them, and StudioStyles accepts them by
 * shape under the consultant/student/teacher roots. These tests pin both
 * halves of that contract — that the markup really emits the ids the canvas
 * needs, and that the boundary accepts exactly those and nothing looser.
 */
class DashboardIdsTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();
        $host = Str::lower(Str::random(10)).'.sapienstech.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    // =========================================================================
    // The boundary
    // =========================================================================

    public function test_dashboard_paths_are_addressable_by_shape(): void
    {
        foreach ([
            'public.consultant.content', 'public.consultant.content.main',
            'public.consultant.heading', 'public.consultant.students.grid',
            'public.student.welcome', 'public.student.schedule',
            'public.teacher.stats',
        ] as $path) {
            $this->assertTrue(StudioStyles::validPath($path), $path);
            $this->assertTrue(StudioStyles::validNodeId($path), $path);
        }
    }

    public function test_dashboard_roots_still_require_a_segment_below_them(): void
    {
        // A bare root names no element, exactly as `public.landing` does not.
        foreach (['public.consultant', 'public.student', 'public.teacher'] as $path) {
            $this->assertFalse(StudioStyles::validPath($path), $path);
        }
    }

    public function test_unknown_roots_and_bad_charsets_stay_rejected(): void
    {
        foreach ([
            'public.dashboard.content',        // not a page root
            'public.consultant.content<x>',    // charset breakout
            'public.consultant.content"',      // selector breakout
            'public.Consultant.content',       // case matters
            'public.consultant..content',      // empty segment
        ] as $path) {
            $this->assertFalse(StudioStyles::validPath($path), $path);
        }
    }

    // =========================================================================
    // The emitted markup
    // =========================================================================

    public function test_consultant_shell_emits_dashboard_ids_and_sections(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);
        $consultant = User::factory()->consultant()->create(['tenant_id' => $tenant->id]);

        $html = $this->actingAs($consultant)
            ->get("http://{$host}/consultant/dashboard")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-studio-path="public.consultant.content"', $html);
        $this->assertStringContainsString('data-studio-path="public.consultant.content.main"', $html);
        $this->assertStringContainsString('data-studio-path="public.consultant.heading"', $html);

        // Sections bind by marker -> nextElementSibling, so each marker must be
        // present and distinct.
        foreach (['nav', 'content', 'heading'] as $section) {
            $this->assertStringContainsString('data-studio-section-marker="'.$section.'"', $html);
        }

        // Every emitted id is one the boundary accepts — no drift between the
        // markup and the whitelist.
        $this->assertEveryEmittedPathIsAddressable($html);
    }

    public function test_student_shell_emits_dashboard_ids(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);
        $student = Student::factory()->for($tenant)->create();

        $html = $this->actingAs($student, 'student')
            ->get("http://{$host}/student/dashboard")
            ->assertOk()
            ->getContent();

        foreach ([
            'public.student.content', 'public.student.welcome',
            'public.student.stats', 'public.student.schedule',
        ] as $path) {
            $this->assertStringContainsString('data-studio-path="'.$path.'"', $html, $path);
        }

        $this->assertStringContainsString('data-studio-section-marker="welcome"', $html);
        $this->assertEveryEmittedPathIsAddressable($html);
    }

    public function test_teacher_shell_emits_dashboard_ids(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);
        $teacher = User::factory()->teacher()->create(['tenant_id' => $tenant->id]);

        $html = $this->actingAs($teacher)
            ->get("http://{$host}/teacher/dashboard")
            ->assertOk()
            ->getContent();

        foreach (['public.teacher.content', 'public.teacher.welcome', 'public.teacher.stats'] as $path) {
            $this->assertStringContainsString('data-studio-path="'.$path.'"', $html, $path);
        }

        $this->assertEveryEmittedPathIsAddressable($html);
    }

    /**
     * The three role shells must each load the canvas stylesheet through the
     * shared partial exactly once, so a node override reaches every dashboard
     * and the emission rule cannot drift per shell.
     */
    public function test_dashboard_shells_load_node_styles_through_the_shared_partial(): void
    {
        foreach (['layouts/consultant', 'layouts/student', 'layouts/teacher'] as $view) {
            $source = file_get_contents(resource_path('views/'.$view.'.blade.php'));

            $this->assertSame(1, substr_count($source, "@include('partials.studio-node-styles'"), $view);
            $this->assertStringNotContainsString('nodesCss', $source, $view);
        }
    }

    // =========================================================================
    // Persistence on a dashboard page
    // =========================================================================

    public function test_a_dashboard_node_persists_and_paints_the_dashboard(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        ConfigWriter::publishForTenant($tenant, ['public.canvas.nodes' => [
            'public.consultant.content.main' => ['props' => ['padding' => 32]],
            'public.student.welcome' => ['flags' => ['hidden' => true]],
        ]]);

        $consultant = User::factory()->consultant()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($consultant)
            ->get("http://{$host}/consultant/dashboard")
            ->assertOk()
            ->assertSee('id="studio-node-styles"', false)
            ->assertSee('[data-studio-path="public.consultant.content.main"]{padding:32px;}', false)
            // The student-only node is emitted too; its selector simply finds
            // no element on this page.
            ->assertSee('[data-studio-path="public.student.welcome"]{display:none;}', false);
    }

    /**
     * Every `data-studio-path` value in the served HTML must be addressable by
     * StudioStyles. This is the drift guard: markup and boundary live in
     * different files, and a renamed id would otherwise silently become
     * un-styleable.
     */
    private function assertEveryEmittedPathIsAddressable(string $html): void
    {
        preg_match_all('/data-studio-path="([^"]+)"/', $html, $matches);

        $this->assertNotEmpty($matches[1], 'the page emitted no studio ids at all');

        foreach (array_unique($matches[1]) as $path) {
            $this->assertTrue(
                StudioStyles::validNodeId($path),
                sprintf('emitted id "%s" is not addressable by StudioStyles', $path),
            );
        }
    }
}
