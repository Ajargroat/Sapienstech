<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteConfig;
use App\Support\StudioSchema;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The topnav/sidebar switch: theme.layout.shell_nav is a tenant-chosen lever
 * (Appearance studio) that decides whether the panel's tabs render in the
 * sticky topnav or in a fixed sidebar rail, in both the consultant and the
 * student shells. The personal ("just for me") layer rides on top for free.
 */
class ShellNavigationTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();
        $host = Str::lower(Str::random(10)).'.sapienstech.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    private function userFor(Tenant $tenant, string $role = 'consultant_staff'): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
    }

    private function studentFor(Tenant $tenant): Student
    {
        app()->instance('tenant', $tenant);

        return Student::factory()->create(['tenant_id' => $tenant->id]);
    }

    /**
     * Complete, valid studio payload (the form submits every field), with
     * dotted-path overrides applied — same contract as AppearanceStudioTest.
     *
     * @param  array<string,mixed>  $overrides
     */
    private function payload(array $overrides = []): array
    {
        $resolved = site();
        $input = [];

        foreach (array_keys(StudioSchema::rules()) as $path) {
            $field = StudioSchema::field($path);
            $value = Arr::get($resolved, $path);

            if (($field['control'] ?? '') === 'toggle') {
                $value = $value ? '1' : '0';
            }

            Arr::set($input, $path, $value);
        }

        foreach ($overrides as $path => $value) {
            Arr::set($input, $path, $value);
        }

        return $input;
    }

    private function publish(Tenant $tenant, string $host, User $admin, string $value): void
    {
        $payload = $this->payload(['theme.layout.shell_nav' => $value]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();
    }

    public function test_the_lever_is_exposed_in_the_studio_layout_group(): void
    {
        $field = StudioSchema::field('theme.layout.shell_nav');

        $this->assertNotNull($field, 'shell_nav must be whitelisted in config/studio.php');
        $this->assertSame('layout', $field['group']);
        $this->assertSame(['topnav' => 'نوار بالا', 'sidebar' => 'نوار کناری'], $field['options']);
    }

    public function test_the_shell_defaults_to_the_topnav(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->userFor($tenant, 'tenant_admin');

        $html = $this->actingAs($user)
            ->get("http://{$host}/consultant/dashboard")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-shell-nav="topnav"', $html);
        $this->assertStringContainsString('class="consultant-topnav"', $html);
        $this->assertStringNotContainsString('consultant-sidebar', $html);
    }

    public function test_tenant_can_switch_the_panel_to_the_sidebar(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $this->publish($tenant, $host, $admin, 'sidebar');

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertSame('sidebar', data_get($row->layout_config, 'theme.layout.shell_nav'));

        $html = $this->actingAs($admin)
            ->get("http://{$host}/consultant/dashboard")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-shell-nav="sidebar"', $html);
        $this->assertStringContainsString('class="consultant-sidebar"', $html);
        $this->assertStringNotContainsString('class="consultant-topnav"', $html);

        // The rail carries the same tabs, so navigation stays complete.
        $this->assertStringContainsString('sidebar-link', $html);
        $this->assertStringContainsString('data-topnav-dropdown', $html);
        $this->assertStringContainsString('id="theme-toggle-btn"', $html);
    }

    public function test_the_choice_applies_to_the_student_portal(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');
        $student = $this->studentFor($tenant);

        $this->publish($tenant, $host, $admin, 'sidebar');

        $html = $this->actingAs($student, 'student')
            ->get("http://{$host}/student/dashboard")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-shell-nav="sidebar"', $html);
        $this->assertStringContainsString('class="consultant-sidebar"', $html);
        $this->assertStringNotContainsString('class="consultant-topnav"', $html);
    }

    public function test_a_user_can_switch_only_their_own_shell(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $staff = $this->userFor($tenant, 'consultant_staff');
        $other = $this->userFor($tenant, 'consultant_staff');

        $payload = $this->payload(['theme.layout.shell_nav' => 'sidebar']);
        $payload['scope'] = 'me';

        $this->actingAs($staff)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $this->assertStringContainsString(
            'class="consultant-sidebar"',
            $this->actingAs($staff)->get("http://{$host}/consultant/dashboard")->getContent()
        );
        $this->assertStringContainsString(
            'class="consultant-topnav"',
            $this->actingAs($other)->get("http://{$host}/consultant/dashboard")->getContent()
        );
    }

    public function test_only_the_two_placements_are_accepted(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload(['theme.layout.shell_nav' => 'bottombar']);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertSessionHasErrors('theme.layout.shell_nav');

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNull(data_get($row?->layout_config, 'theme.layout.shell_nav'));
    }

    public function test_resetting_the_key_falls_back_to_the_topnav(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $this->publish($tenant, $host, $admin, 'sidebar');

        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance/reset", [
            'path' => 'theme.layout.shell_nav',
            'scope' => 'everyone',
        ])->assertRedirect();

        $html = $this->actingAs($admin)
            ->get("http://{$host}/consultant/dashboard")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-shell-nav="topnav"', $html);
        $this->assertStringContainsString('class="consultant-topnav"', $html);
    }
}
