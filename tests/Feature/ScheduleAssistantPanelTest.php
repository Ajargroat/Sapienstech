<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The assistant panel must actually appear on the planner page — and must
 * disappear cleanly when the tenant turns it off.
 *
 * This exists because the panel's markup is the one part of the feature a
 * controller test cannot see: everything else was verified through JSON, but
 * "is there a button" is a question about the rendered document. It also pins
 * the wiring the module depends on (the URLs and the input the composer needs),
 * since a renamed id would silently break the assistant while every other test
 * stayed green.
 */
class ScheduleAssistantPanelTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: string} */
    private function tenantWithDomain(string $host): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    public function test_the_panel_and_its_entry_point_render_on_the_planner(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('ai-panel.test');
        app()->instance('tenant', $tenant);

        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_ADMIN]);
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($staff)
            ->get("http://{$host}/consultant/students/{$student->id}/schedule");

        $response->assertOk();

        // The entry point and the panel itself.
        $response->assertSee('assistant-toggle', false);
        $response->assertSee('assistant-panel', false);

        // The elements the assistant module drives. A rename here would break
        // the feature silently, which is exactly what this test is for.
        foreach (['assistant-log', 'assistant-input', 'assistant-send-button',
            'assistant-briefing-button', 'assistant-clear-button', 'assistant-status',
            'assistant-meter-fill', 'assistant-meter-text'] as $id) {
            $response->assertSee($id, false);
        }

        // The URLs, wired as data attributes rather than hardcoded in the module.
        $response->assertSee('data-url-assistant', false);
        $response->assertSee('data-url-assistant-send', false);
        $response->assertSee('data-url-assistant-clear', false);
        $response->assertSee('data-url-assistant-health', false);
    }

    public function test_the_panel_is_absent_when_the_tenant_disables_the_assistant(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('ai-panel-off.test');
        app()->instance('tenant', $tenant);

        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_ADMIN]);
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);

        site_override(['ai' => ['enabled' => false]]);

        $response = $this->actingAs($staff)
            ->get("http://{$host}/consultant/students/{$student->id}/schedule");

        // The planner still works; only the assistant is gone. A tenant that
        // turns it off must not lose the page the consultant actually needs.
        $response->assertOk();

        // Asserting on the ELEMENT, not the string: the panel's CSS rules also
        // mention `#assistant-panel`, so a bare substring check would be
        // meaningless here. The id attribute is the honest signal.
        $response->assertDontSee('id="assistant-panel"', false);
        // The entry point must go too. An unguarded button would be a dead
        // control: it would render, and opening it would reveal nothing.
        $response->assertDontSee('id="assistant-toggle"', false);
        // And the page must not advertise routes the panel cannot use.
        $response->assertDontSee('data-url-assistant', false);

        // The planner shell itself is untouched.
        $response->assertSee('schedule-app', false);
    }

    public function test_the_panel_and_button_are_rendered_when_enabled(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('ai-panel-on.test');
        app()->instance('tenant', $tenant);

        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_ADMIN]);
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($staff)
            ->get("http://{$host}/consultant/students/{$student->id}/schedule");

        // The positive case, so the negative assertion above cannot pass because
        // the selector is simply wrong.
        $response->assertOk();
        $response->assertSee('id="assistant-panel"', false);
        $response->assertSee('id="assistant-toggle"', false);
    }

    public function test_the_provider_endpoint_and_key_are_not_rendered_into_the_page(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('ai-panel-secrets.test');
        app()->instance('tenant', $tenant);

        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_ADMIN]);
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);

        config([
            'services.ai.keys' => ['sk-must-not-appear-in-html'],
            'services.ai.base_url' => 'https://secret-provider.example.test/v1',
        ]);

        $html = $this->actingAs($staff)
            ->get("http://{$host}/consultant/students/{$student->id}/schedule")
            ->getContent();

        // The browser must never hold either secret: every call is proxied
        // server-side, so neither may appear in the document.
        $this->assertStringNotContainsString('sk-must-not-appear-in-html', $html);
        $this->assertStringNotContainsString('secret-provider.example.test', $html);
    }
}
