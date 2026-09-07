<?php

namespace Tests\Feature\Consultant;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteConfig;
use App\Support\ConfigWriter;
use App\Support\StudioSchema;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The appearance studio: schema-whitelisted writes, the everyone/me/preview
 * scopes, the tenant-admin gate (decision #1), per-key reset, and rejection
 * of non-whitelisted paths and malformed values.
 */
class AppearanceStudioTest extends TestCase
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

    /**
     * Build a complete, valid studio payload from the current resolved config
     * (the form always submits every field), then apply dotted-path overrides.
     *
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>  nested input
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

    public function test_appearance_tab_loads(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->userFor($tenant, 'tenant_admin');

        $response = $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/appearance")
            ->assertOk()
            ->assertSee('ظاهر');

        $html = $response->getContent();

        // Spot-check that the expanded schema reaches the form: a behaviour
        // lever, a landing-copy lever, a variant select and a feature switch.
        foreach ([
            'data-studio-field="theme.motion.text_effect"',
            'data-studio-field="public.landing.hero.title_line1"',
            'data-studio-field="public.landing.stats.variant"',
            'data-studio-field="features.student_profile"',
        ] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
    }

    public function test_tenant_admin_can_publish_site_wide_change(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload(['theme.colors.primary' => '#112233']);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertSame('#112233', data_get($row->layout_config, 'theme.colors.primary'));

        // And it reaches the rendered theme for any visitor.
        $this->assertStringContainsString('--c-primary: #112233', $this->get("http://{$host}/consultant/dashboard")->getContent());
    }

    public function test_staff_cannot_publish_site_wide_by_default(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $staff = $this->userFor($tenant, 'consultant_staff');

        $payload = $this->payload(['theme.colors.primary' => '#999999']);
        $payload['scope'] = 'everyone';

        $this->actingAs($staff)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertForbidden();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNull(data_get($row?->layout_config, 'theme.colors.primary'));
    }

    public function test_admin_can_grant_staff_site_wide_publishing(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');
        $staff = $this->userFor($tenant, 'consultant_staff');

        // Admin enables staff publishing.
        $grant = $this->payload(['features.appearance_staff_publish' => true]);
        $grant['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $grant)->assertRedirect();

        // Now staff can publish.
        $payload = $this->payload(['theme.colors.secondary' => '#00FF00']);
        $payload['scope'] = 'everyone';
        $this->actingAs($staff)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertSame('#00FF00', data_get($row->layout_config, 'theme.colors.secondary'));
    }

    public function test_staff_can_save_personal_theme(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $staff = $this->userFor($tenant, 'consultant_staff');
        $other = $this->userFor($tenant, 'consultant_staff');

        $payload = $this->payload(['theme.colors.primary' => '#ABCDEF']);
        $payload['scope'] = 'me';

        $this->actingAs($staff)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $this->assertSame('#ABCDEF', data_get($staff->fresh()->preferences, 'site.theme.colors.primary'));

        // Only the owner sees it.
        $this->assertStringContainsString('--c-primary: #ABCDEF', $this->actingAs($staff)->get("http://{$host}/consultant/dashboard")->getContent());
        $this->assertStringNotContainsString('--c-primary: #ABCDEF', $this->actingAs($other)->get("http://{$host}/consultant/dashboard")->getContent());
    }

    public function test_non_whitelisted_path_is_ignored(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload();
        $payload['scope'] = 'everyone';
        // Attempt to smuggle an arbitrary key through.
        Arr::set($payload, 'app.debug', true);

        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $payload)->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNull(data_get($row?->layout_config, 'app.debug'));
        $this->assertArrayNotHasKey('app.debug', (array) ($row?->layout_config ?? []));
    }

    public function test_malformed_color_is_rejected(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload(['theme.colors.primary' => 'not-a-color']);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertSessionHasErrors('theme.colors.primary');
    }

    public function test_reset_key_forgets_the_override(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload(['theme.colors.primary' => '#112233']);
        $payload['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $payload);

        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance/reset", [
            'path' => 'theme.colors.primary',
            'scope' => 'everyone',
        ])->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNull(data_get($row->layout_config, 'theme.colors.primary'));
    }

    public function test_reset_all_clears_the_tenant_layer(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload(['theme.colors.primary' => '#112233', 'theme.colors.secondary' => '#445566']);
        $payload['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $payload);

        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance/reset-all")->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertEmpty($row->layout_config);
    }

    public function test_preview_writes_session_not_database(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload(['theme.colors.primary' => '#FEFEFE']);
        $payload['scope'] = 'preview';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        // Nothing persisted to the DB layer.
        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNull(data_get($row?->layout_config, 'theme.colors.primary'));

        // But the previewer's own requests render it (session layer).
        $this->assertStringContainsString('--c-primary: #FEFEFE', $this->actingAs($admin)->get("http://{$host}/consultant/dashboard")->getContent());

        // Exit preview stops it.
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance/preview/exit")->assertRedirect();
        $this->assertStringNotContainsString('--c-primary: #FEFEFE', $this->actingAs($admin)->get("http://{$host}/consultant/dashboard")->getContent());
    }

    public function test_features_group_is_not_writable_by_staff(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $staff = $this->userFor($tenant, 'consultant_staff');

        $payload = $this->payload();
        $payload['scope'] = 'me';
        // staff tries to toggle a feature off
        Arr::set($payload, 'features.dashboard', '0');

        $this->actingAs($staff)->post("http://{$host}/consultant/settings/appearance", $payload)->assertRedirect();

        // The feature toggle was dropped (admin-only), so dashboard still enabled.
        $this->assertNotSame('0', data_get($staff->fresh()->preferences, 'site.features.dashboard'));
    }

    /**
     * Landing sections are freely reorderable, but locked ones (hero) are
     * re-pinned by StudioSchema::pinSections() on the way in — a submission
     * that moves or drops them cannot reach the DB.
     */
    public function test_locked_sections_stay_pinned_and_present(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        // hero demoted to the middle; blog promoted to first.
        $payload = $this->payload([
            'public.landing.sections' => ['blog', 'advisor', 'hero', 'cta'],
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertSame(
            ['hero', 'blog', 'advisor', 'cta'],
            data_get($row->layout_config, 'public.landing.sections')
        );

        // Dropping hero entirely is not a hide: it is re-inserted.
        $payload = $this->payload(['public.landing.sections' => ['services', 'stats']]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertSame(
            ['hero', 'services', 'stats'],
            data_get($row->layout_config, 'public.landing.sections')
        );
    }

    /**
     * The studio form submits *every* field on every save, so a field whose
     * rules reject its own resolved default would brick all saving for that
     * tenant. This asserts schema self-consistency under every archetype.
     */
    public function test_every_field_accepts_its_resolved_value_in_every_archetype(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $this->userFor($tenant, 'tenant_admin');

        foreach ([null, ...StudioSchema::archetypes()] as $archetype) {
            // The archetype is decided from the DB layer (runtime overrides
            // land too late), so switch it the same way the studio does.
            ConfigWriter::publishForTenant($tenant, ['theme.archetype' => $archetype]);

            $resolved = site();

            $this->assertSame(
                $archetype ?? 'aurora_glass',
                data_get($resolved, 'theme.archetype'),
                'archetype layer did not take effect'
            );

            foreach (StudioSchema::fields() as $path => $field) {
                if (($field['control'] ?? '') === 'image') {
                    continue; // uploads validate as files, not scalars
                }

                $value = Arr::get($resolved, $path);
                $rules = StudioSchema::rules()[$path];

                $this->assertTrue(
                    Validator::make(['v' => $value], ['v' => $rules])->passes(),
                    sprintf(
                        'resolved value %s for "%s" fails its own rules under archetype "%s"',
                        json_encode($value, JSON_UNESCAPED_UNICODE),
                        $path,
                        $archetype ?? 'baseline',
                    )
                );
            }
        }
    }

    /**
     * Clearing an optional field must forget the override (null), never store
     * an empty string that would poison a CSS custom property.
     */
    public function test_clearing_an_optional_field_forgets_the_override(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload(['public.landing.hero.eyebrow' => 'یک برچسب آزمایشی']);
        $payload['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $payload)->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertSame('یک برچسب آزمایشی', data_get($row->layout_config, 'public.landing.hero.eyebrow'));

        // Now clear it: '' normalizes to null, which forgets the key again.
        $payload = $this->payload(['public.landing.hero.eyebrow' => '']);
        $payload['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $payload)->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNull(data_get($row->layout_config, 'public.landing.hero.eyebrow'));
    }
}
