<?php

namespace Tests\Feature\Consultant;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteConfig;
use App\Support\ConfigWriter;
use App\Support\StudioSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;

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

    public function test_appearance_section_loads_inside_the_profile_hub(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->userFor($tenant, 'tenant_admin');

        $response = $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/profile?tab=appearance")
            ->assertOk()
            ->assertSee('ظاهر');

        // The old standalone tab URL redirects into the hub section.
        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/appearance")
            ->assertRedirect('/consultant/settings/profile?tab=appearance');

        $html = $response->getContent();

        // Spot-check that the expanded schema reaches the form: a behaviour
        // lever, a landing-copy lever, a variant select, a feature switch and
        // a list repeater with its stored rows.
        foreach ([
            'data-studio-field="theme.motion.text_effect"',
            'data-studio-field="public.landing.hero.title_line1"',
            'data-studio-field="public.landing.stats.variant"',
            'data-studio-field="features.student_profile"',
            'data-studio-field="public.landing.services.items"',
            'data-studio-field="public.landing.blocks.items"',
            'data-list-template',
            'studio-workspace',
            'data-workspace-tool="select"',
            'data-workspace-insert="button"',
            'data-object-inspector',
            'data-object-metrics',
            'data-studio-preview-device="tablet"',
            'data-studio-zoom',
        ] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }

        // The baseline placeholder service card renders as an editable row.
        $this->assertStringContainsString(
            'public[landing][services][items][0][title]',
            $html
        );
    }

    public function test_icon_set_is_selectable_in_the_studio_with_visual_previews(): void
    {
        [$otherTenant, $otherHost] = $this->tenantWithDomain();
        $otherAdmin = $this->userFor($otherTenant, 'tenant_admin');
        ConfigWriter::publishForTenant($otherTenant, ['theme.icons.set' => 'lucide']);

        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $html = $this->actingAs($admin)
            ->get("http://{$host}/consultant/settings/profile?tab=appearance")
            ->getContent();

        // The lever exists in the form and every choice renders a real icon
        // preview, not a plain name list.
        $this->assertStringContainsString('data-studio-field="theme.icons.set"', $html);
        $this->assertStringContainsString('data-studio-iconset="lucide"', $html);
        $this->assertStringContainsString('data-studio-iconset="font-awesome"', $html);
        $this->assertSame(5, substr_count($html, 'studio-iconset-demo'));
        $this->assertSame(32, substr_count($html, 'class="studio-iconset-glyph"'));
        $this->assertSame(8, substr_count($html, 'class="studio-iconset-fa '));
        $this->assertStringNotContainsString('<img src=""', $html);
        $this->assertSame('reload', StudioSchema::liveMode('theme.icons.set'));

        // Choosing a set publishes it to the tenant layer and the site renders
        // that set's stylesheet.
        $payload = $this->payload(['theme.icons.set' => 'tabler']);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertSame('tabler', data_get($row->layout_config, 'theme.icons.set'));

        $this->assertStringContainsString(
            'icons/tabler.css',
            $this->get("http://{$host}/consultant/dashboard")->getContent()
        );

        $otherHtml = $this->actingAs($otherAdmin)
            ->get("http://{$otherHost}/consultant/dashboard")->assertOk()->getContent();
        $this->assertStringContainsString('data-icon-set="lucide"', $otherHtml);
        $this->assertStringNotContainsString('icons/tabler.css', $otherHtml);
    }

    public function test_invalid_and_retired_icon_sets_are_rejected_by_the_studio(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');
        $this->actingAs($admin)->get("http://{$host}/consultant/dashboard")->assertOk();

        foreach (['feather', 'heroicons', '../other', 'unknown'] as $set) {
            $payload = $this->payload(['theme.icons.set' => $set]);
            $payload['scope'] = 'everyone';
            $this->post("http://{$host}/consultant/settings/appearance", $payload)
                ->assertSessionHasErrors('theme.icons.set');
        }

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNull(data_get($row?->layout_config, 'theme.icons.set'));
    }

    public function test_icon_preview_is_session_only_and_can_be_saved_personally(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $staff = $this->userFor($tenant);
        $this->actingAs($staff)->get("http://{$host}/consultant/dashboard")->assertOk();
        $payload = $this->payload(['theme.icons.set' => 'bi']);
        $payload['scope'] = 'preview';
        $this->postJson("http://{$host}/consultant/settings/appearance/live", $payload)->assertOk();
        $this->assertSame('bi', session('studio.preview.theme.icons.set'));
        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNull(data_get($row?->layout_config, 'theme.icons.set'));
        $this->assertNull(data_get($staff->fresh()->preferences, 'site.theme.icons.set'));

        $payload['scope'] = 'me';
        $this->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('bi', data_get($staff->fresh()->preferences, 'site.theme.icons.set'));
        $this->assertNull(session('studio.preview'));
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

                // The form submits every stored row on every save, so a
                // baseline row that rejects its own item rules would brick
                // the whole list for that tenant.
                if (($field['control'] ?? '') === 'list') {
                    foreach ((array) $value as $i => $row) {
                        foreach ($field['item'] as $def) {
                            $itemRules = ($def['control'] ?? '') === 'toggle'
                                ? ['nullable', 'boolean']
                                : ($def['rules'] ?? []);

                            $this->assertTrue(
                                Validator::make(['v' => $row[$def['key']] ?? null], ['v' => $itemRules])->passes(),
                                sprintf(
                                    'baseline row %d key "%s" of "%s" fails its item rules under archetype "%s"',
                                    $i,
                                    $def['key'],
                                    $path,
                                    $archetype ?? 'baseline',
                                )
                            );
                        }
                    }
                }
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

    /**
     * The list control: a tenant edits a stored row, hides it, and adds a
     * fresh one under a browser-side key. The saved override is a sequential
     * list of whitelisted keys only — the temporary key never survives.
     */
    public function test_list_rows_can_be_edited_hidden_and_added(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload([
            'public.landing.services.items' => [
                0 => ['title' => 'کارت ویرایش‌شده', 'text' => 'توضیح ویرایش‌شده', 'icon' => 'fa-solid fa-bolt', 'accent' => 'primary', 'visible' => '0'],
                'n1-abc' => ['title' => 'کارت تازه', 'text' => 'توضیح تازه', 'accent' => 'secondary', 'visible' => '1'],
            ],
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $items = data_get(
            WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config,
            'public.landing.services.items'
        );

        $this->assertIsArray($items);
        $this->assertSame([0, 1], array_keys($items), 'rows must be re-indexed sequentially');
        $this->assertSame('کارت ویرایش‌شده', $items[0]['title']);
        $this->assertFalse($items[0]['visible'], 'unchecked row toggle must store false');
        $this->assertSame('کارت تازه', $items[1]['title']);
        $this->assertTrue($items[1]['visible']);
        $this->assertArrayNotHasKey('icon', $items[1], 'absent optional keys must not be materialized');
    }

    /**
     * An abandoned "add row" is all-empty until typed into; it is dropped
     * before validation so it neither fails the row's required rules nor
     * reaches the stored layer.
     */
    public function test_empty_list_rows_are_dropped(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $baseline = Arr::get(site(), 'public.landing.services.items');

        $payload = $this->payload([
            'public.landing.services.items' => [
                ...$baseline,
                'n9-empty' => ['title' => '', 'text' => '', 'icon' => '', 'accent' => 'primary', 'visible' => '0'],
            ],
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

        // The list is unchanged relative to the baseline, so nothing about it
        // is stored at all — the empty row left no trace.
        $this->assertNull(data_get($row?->layout_config, 'public.landing.services.items'));
    }

    /**
     * The form submits every field on every save, so an untouched list must
     * produce no override — including rows whose baseline carries keys the
     * control omits (hero buttons' null icon).
     */
    public function test_untouched_lists_produce_no_override(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload();
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertEmpty($row?->layout_config, 'a no-op save must not store any override');
    }

    /**
     * Row values validate against the schema's wildcard item rules; the
     * group reopens with the first row error surfaced.
     */
    public function test_invalid_list_row_value_is_rejected(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload([
            'public.landing.services.items' => [
                ['title' => '', 'text' => 'فقط توضیح پر شده', 'accent' => 'primary', 'visible' => '1'],
            ],
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertSessionHasErrors('public.landing.services.items.0.title');
    }

    /**
     * A submission may not smuggle more rows than the schema's max, nor
     * unknown keys into a row.
     */
    public function test_list_size_is_capped_and_unknown_keys_dropped(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $row = ['label' => 'لینک', 'href' => '#', 'visible' => '1'];
        $payload = $this->payload([
            'public.nav.links' => array_fill(0, 9, $row), // max is 8
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertSessionHasErrors('public.nav.links');

        // Unknown per-row keys are stripped by normalization, not stored.
        $payload = $this->payload([
            'public.nav.links' => [
                ['label' => 'لینک سالم', 'href' => '#x', 'visible' => '1', 'evil' => 'payload'],
            ],
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $stored = data_get(
            WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config,
            'public.nav.links.0'
        );
        $this->assertArrayNotHasKey('evil', $stored);
    }

    /**
     * Resetting a list path forgets the whole override, so the file-owned
     * items show through again.
     */
    public function test_reset_forgets_the_whole_list(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload([
            'public.landing.faq.items' => [
                ['question' => 'پرسش آزمایشی', 'answer' => 'پاسخ آزمایشی', 'visible' => '1'],
            ],
        ]);
        $payload['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $payload)->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull(data_get($row->layout_config, 'public.landing.faq.items'));

        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance/reset", [
            'path' => 'public.landing.faq.items',
            'scope' => 'everyone',
        ])->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNull(data_get($row->layout_config, 'public.landing.faq.items'));
    }

    /**
     * The typed list (blocks): rows of different shapes coexist, each keeping
     * only the cells its own type owns. Untouched optional selects submit ''
     * and are dropped, so the renderer falls back to theme defaults; a
     * content-free type (spacer, divider) survives on its own; and the
     * landing page renders the composed section end to end.
     */
    public function test_blocks_section_can_be_composed_and_rendered(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload([
            'public.landing.sections' => [...Arr::get(site(), 'public.landing.sections'), 'blocks'],
            'public.landing.blocks.items' => [
                ['type' => 'heading', 'title' => 'تیتر سفارشی', 'text' => 'زیرتیتر سفارشی', 'align' => 'center', 'href' => '#نشتی', 'visible' => '1'],
                ['type' => 'button', 'title' => 'دکمه سفارشی', 'href' => '/contact', 'style' => '', 'icon' => '', 'visible' => '1'],
                ['type' => 'spacer', 'size' => 'sm', 'visible' => '1'],
                ['type' => 'divider', 'visible' => '1'],
            ],
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $items = data_get(
            WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config,
            'public.landing.blocks.items'
        );

        $this->assertCount(4, $items);
        // Cells the row's type does not own are dropped, even though the form
        // submitted them (hidden inputs still post their stale values).
        $this->assertSame(['type', 'title', 'text', 'align', 'visible'], array_keys($items[0]));
        $this->assertSame('heading', $items[0]['type']);
        $this->assertTrue($items[0]['visible']);
        // '' selects normalize away so the theme default shows through.
        $this->assertSame(['type', 'title', 'href', 'visible'], array_keys($items[1]));
        $this->assertSame(['type', 'size', 'visible'], array_keys($items[2]));
        $this->assertSame(['type', 'visible'], array_keys($items[3]));

        $html = $this->get("http://{$host}/")->assertOk()->getContent();
        $this->assertStringContainsString('تیتر سفارشی', $html);
        $this->assertStringContainsString('دکمه سفارشی', $html);
        $this->assertStringContainsString('href="/contact"', $html);
        $this->assertStringNotContainsString('#نشتی', $html, 'a stale cell from another type must never render');
    }

    /**
     * Type-gated requiredness: a heading without its title or a button
     * without its href fails with a concrete per-row error, while the same
     * empty cell on a type that does not use it is neither validated nor
     * stored. A forged type is rejected by the discriminant's own rules.
     */
    public function test_block_cells_validate_per_row_type(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload([
            'public.landing.blocks.items' => [
                ['type' => 'heading', 'title' => '', 'text' => 'فقط زیرتیتر', 'visible' => '1'],
                ['type' => 'button', 'title' => 'بدون نشانی', 'href' => '', 'visible' => '1'],
            ],
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertSessionHasErrors([
                'public.landing.blocks.items.0.title',
                'public.landing.blocks.items.1.href',
            ]);

        // A spacer carries no content cells at all — it must pass untouched.
        $payload = $this->payload([
            'public.landing.blocks.items' => [
                ['type' => 'spacer', 'title' => '', 'text' => '', 'href' => '', 'visible' => '1'],
            ],
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        // Unknown type: the discriminant's in: rule is the whitelist.
        $payload = $this->payload([
            'public.landing.blocks.items' => [
                ['type' => 'evil', 'title' => 'x', 'visible' => '1'],
            ],
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertSessionHasErrors('public.landing.blocks.items.0.type');
    }

    /**
     * An abandoned "add row" in a typed list defaults to the first block
     * type with no content: it is dropped like any other empty row. A row
     * the tenant did fill survives, keyed by its browser-side temp key.
     */
    public function test_abandoned_block_rows_are_dropped(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload([
            'public.landing.blocks.items' => [
                'n1-abc' => ['type' => 'heading', 'title' => 'بلوک واقعی', 'text' => '', 'align' => '', 'visible' => '1'],
                'n2-def' => ['type' => 'heading', 'title' => '', 'text' => '', 'align' => '', 'visible' => '0'],
            ],
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect();

        $items = data_get(
            WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config,
            'public.landing.blocks.items'
        );

        $this->assertCount(1, $items);
        $this->assertSame([0], array_keys($items), 'the abandoned row must leave no gap');
        $this->assertSame('بلوک واقعی', $items[0]['title']);
    }

    public function test_block_styles_publish_individually_with_stable_render_markers(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        [$otherTenant, $otherHost] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');
        $id = (string) Str::uuid();
        $buttonId = (string) Str::uuid();
        $styles = ['background' => '#123ABC', 'color' => '#FEDCBA', 'padding' => '0', 'radius' => '128', 'width' => '75'];
        $payload = $this->payload([
            'public.landing.sections' => ['hero', 'blocks'],
            'public.landing.blocks.items' => [
                ['type' => 'text', 'text' => 'Hidden block', 'visible' => '0'],
                ['type' => 'card', 'title' => 'Styled card', 'text' => 'Card copy', 'id' => $id, 'visible' => '1', ...$styles],
                ['type' => 'button', 'title' => 'Styled button', 'href' => '/contact', 'id' => $buttonId, 'background' => '#AABBCC', 'padding' => '12', 'visible' => '1'],
                ['type' => 'text', 'text' => 'Unstyled sibling', 'visible' => '1'],
                ['type' => 'heading', 'title' => 'Legacy heading', 'visible' => '1'],
                ['type' => 'image', 'src' => '/block.png', 'visible' => '1'],
                ['type' => 'spacer', 'visible' => '1'],
                ['type' => 'divider', 'visible' => '1'],
            ],
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $items = data_get(WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config, 'public.landing.blocks.items');
        $this->assertCount(8, $items);
        $this->assertSame($id, $items[1]['id']);
        foreach (['background' => '#123ABC', 'color' => '#FEDCBA', 'padding' => 0, 'radius' => 128, 'width' => 75] as $key => $value) {
            $this->assertSame($value, $items[1][$key]);
            $this->assertArrayNotHasKey($key, $items[3]);
        }
        $this->assertArrayNotHasKey('id', $items[3]);
        $this->assertSame(['type' => 'spacer', 'visible' => true], $items[6]);

        $this->app['auth']->guard()->logout();
        $html = $this->get("http://{$host}/")->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $objects = $xpath->query('//*[@data-studio-block]');
        $this->assertCount(7, $objects);
        $this->assertSame([$id, $buttonId, 'legacy-3', 'legacy-4', 'legacy-5', 'legacy-6', 'legacy-7'],
            array_map(static fn ($node) => $node->getAttribute('data-studio-block'), iterator_to_array($objects)));
        $this->assertSame('display:contents;--block-background:#123ABC;--block-color:#FEDCBA;--block-padding:0px;--block-radius:128px;--block-width:75%', $objects[0]->getAttribute('style'));
        $this->assertSame('display:contents;--block-background:#AABBCC;--block-padding:12px', $objects[1]->getAttribute('style'));
        $this->assertSame('display:contents', $objects[2]->getAttribute('style'));
        $this->assertSame(1, $xpath->query('//*[@data-studio-block="'.$id.'"]/*[contains(@class,"lp-blocks__card")]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-studio-block="'.$buttonId.'"]/*[contains(@class,"lp-blocks__buttons")]/a[contains(@class,"lp-btn")]')->length);
        $this->assertStringNotContainsString('Hidden block', $html);
        $this->assertSame(2, substr_count($html, '--block-background:'));

        $otherHtml = $this->get("http://{$otherHost}/")->assertOk()->getContent();
        $this->assertStringNotContainsString($id, $otherHtml);
        $this->assertStringNotContainsString('--block-background:#123ABC', $otherHtml);
        $this->assertNull(data_get(WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $otherTenant->id)->first()?->layout_config, 'public.landing.blocks.items'));
    }

    public function test_invalid_block_styles_and_ids_are_rejected_without_persisting(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');
        $invalid = [
            ['background', '#123'], ['background', '#123456;color:red'],
            ['color', 'red'], ['color', ['#123456']],
            ['padding', -1], ['padding', 129], ['padding', '1.5'], ['padding', '12px'],
            ['padding', true], ['padding', ['12']],
            ['radius', -1], ['radius', 129], ['radius', '1e2'], ['radius', '+12'],
            ['width', 9], ['width', 101], ['width', '50%'], ['width', 10.5],
            ['id', 'legacy-0'], ['id', 'not-a-uuid'],
        ];
        $rows = [];
        $errors = [];
        foreach ($invalid as $i => [$key, $value]) {
            $rows[] = ['type' => 'card', 'title' => 'Invalid style', 'visible' => '1', $key => $value];
            $errors[] = 'public.landing.blocks.items.'.$i.'.'.$key;
        }
        $payload = $this->payload(['public.landing.blocks.items' => $rows]);
        $payload['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertSessionHasErrors($errors);
        $this->assertNull(data_get(WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()?->layout_config, 'public.landing.blocks.items'));
    }

    public function test_block_metadata_does_not_keep_abandoned_content_rows(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');
        $metadata = ['id' => (string) Str::uuid(), 'background' => '#123456', 'color' => '#ABCDEF', 'padding' => 0, 'radius' => 20, 'width' => 100];
        $rows = [];
        foreach (['heading', 'text', 'button', 'card', 'image'] as $type) {
            $rows[] = ['type' => $type, 'visible' => '1', ...$metadata];
        }
        $rows[] = ['type' => 'spacer', 'visible' => '1'];
        $rows[] = ['type' => 'divider', 'visible' => '1'];
        $rows[] = ['type' => 'spacer', 'visible' => '1', ...$metadata];
        $payload = $this->payload(['public.landing.blocks.items' => $rows]);
        $payload['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $items = data_get(WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config, 'public.landing.blocks.items');
        $this->assertCount(3, $items);
        $this->assertSame(['type' => 'spacer', 'visible' => true], $items[0]);
        $this->assertSame(['type' => 'divider', 'visible' => true], $items[1]);
        $this->assertSame($metadata['id'], $items[2]['id']);
    }

    public function test_block_style_preview_leaves_published_database_and_preferences_unchanged(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');
        $id = (string) Str::uuid();
        $item = ['type' => 'card', 'title' => 'Published card', 'id' => $id, 'background' => '#112233', 'visible' => '1'];
        $payload = $this->payload(['public.landing.sections' => ['hero', 'blocks'], 'public.landing.blocks.items' => [$item]]);
        $payload['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $before = $row->layout_config;
        $preferences = $admin->fresh()->preferences;

        $preview = $this->payload(['public.landing.blocks.items' => [[...$item, 'background' => '#A1B2C3', 'padding' => 24]]]);
        $preview['scope'] = 'preview';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $preview)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($before, $row->fresh()->layout_config);
        $this->assertSame($preferences, $admin->fresh()->preferences);
        $this->get("http://{$host}/")->assertOk()
            ->assertSee('data-studio-block="'.$id.'"', false)
            ->assertSee('--block-background:#A1B2C3;--block-padding:24px', false);
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance/preview/exit")->assertRedirect();
        $this->get("http://{$host}/")->assertOk()
            ->assertSee('--block-background:#112233', false)
            ->assertDontSee('--block-background:#A1B2C3', false);
    }

    public function test_empty_block_style_fields_reset_only_that_objects_overrides(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');
        $id = (string) Str::uuid();
        $item = ['type' => 'card', 'title' => 'Reset styles', 'id' => $id, 'visible' => '1'];
        $sibling = ['type' => 'text', 'text' => 'Keep sibling style', 'background' => '#ABCDEF', 'visible' => '1'];
        $styles = ['background' => '#112233', 'color' => '#445566', 'padding' => 128, 'radius' => 0, 'width' => 10];
        $payload = $this->payload(['public.landing.sections' => ['hero', 'blocks'], 'public.landing.blocks.items' => [[...$item, ...$styles], $sibling]]);
        $payload['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $reset = $this->payload(['public.landing.blocks.items' => [[...$item, ...array_fill_keys(array_keys($styles), '')], $sibling]]);
        $reset['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $reset)
            ->assertRedirect()->assertSessionHasNoErrors();
        $items = data_get(WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config, 'public.landing.blocks.items');
        foreach (array_keys($styles) as $key) {
            $this->assertArrayNotHasKey($key, $items[0]);
        }
        $this->assertSame($id, $items[0]['id']);
        $this->assertSame('#ABCDEF', $items[1]['background']);
        $this->get("http://{$host}/")->assertOk()
            ->assertSee('data-studio-block="'.$id.'" style="display:contents"', false)
            ->assertDontSee('--block-background:#112233', false)
            ->assertSee('--block-background:#ABCDEF', false);
    }

    public function test_block_style_rendering_filters_unvalidated_file_owned_values(): void
    {
        $this->assertSame('', \App\Support\BlockStyles::variables([
            'background' => '#123456;display:none', 'color' => ['red'],
            'padding' => true, 'radius' => -1, 'width' => 101, 'css' => 'display:none',
        ]));
        $this->assertSame('--block-padding:0px;--block-radius:128px;--block-width:100%',
            \App\Support\BlockStyles::variables(['padding' => 0, 'radius' => '128', 'width' => 100]));
        $defs = array_column(StudioSchema::field('public.landing.blocks.items')['item'], null, 'key');
        $this->assertSame('hidden', $defs['id']['control']);
    }

    public function test_element_overrides_publish_a_scoped_stylesheet(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');
        $payload = $this->payload([
            'public.landing.overrides' => [[
                'path' => 'public.landing.hero.title_line1',
                'background' => '#112233', 'color' => '#AABBCC',
                'radius' => 8, 'padding' => 12, 'width' => 60, 'font_scale' => 150,
                'visible' => '1',
            ]],
        ]);
        $payload['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/consultant/settings/appearance", $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $css = \App\Support\StudioStyles::css(
            data_get(WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config, 'public.landing.overrides')
        );
        $this->assertSame(
            '[data-studio-path="public.landing.hero.title_line1"]{background:#112233;color:#AABBCC;border-radius:8px;padding:12px;inline-size:60%;font-size:150%;}',
            $css
        );
        $this->get("http://{$host}/")->assertOk()
            ->assertSee('id="lp-element-overrides"', false)
            ->assertSee('[data-studio-path="public.landing.hero.title_line1"]', false);
    }

    public function test_element_overrides_reject_unaddressable_paths_and_unsafe_values(): void
    {
        // Only schema-published paths may be addressed. The page-level roots
        // name no element, so they are rejected along with unknown keys.
        foreach (['public.landing.hero.does_not_exist', 'public.landing', 'public.nav', 'public.footer', 'theme.colors.text'] as $path) {
            $this->assertFalse(\App\Support\StudioStyles::validPath($path), $path);
        }
        // Leaf fields, list rows and the containers a template emits.
        foreach (['public.landing.hero.title_line1', 'public.landing.hero.buttons.0', 'public.nav.cta.label', 'public.landing.hero.buttons', 'public.nav.cta', 'public.landing.hero'] as $path) {
            $this->assertTrue(\App\Support\StudioStyles::validPath($path), $path);
        }

        // A file-owned or tampered row cannot smuggle CSS regardless of shape.
        $this->assertSame('', \App\Support\StudioStyles::css([
            ['path' => 'public.landing.hero.title_line1', 'background' => 'red;}html{display:none'],
            ['path' => 'public.landing.hero.title_line1', 'color' => '#GGGGGG'],
            ['path' => 'public.landing.hero.title_line1', 'radius' => '8px'],
            ['path' => 'public.landing.hero.title_line1', 'radius' => 999],
            ['path' => 'public.landing.hero.title_line1', 'width' => 5],
            ['path' => 'public.landing.hero.title_line1', 'font_scale' => '150px'],
            ['path' => 'public.landing.hero.title_line1', 'color' => '#12345'],
            ['path' => 'theme.colors.primary', 'color' => '#123456'],
            ['path' => 'public.landing.hero.title_line1<string>', 'color' => '#123456'],
            ['path' => 'public.landing', 'color' => '#123456'],
            ['nope' => true],
        ]));

        // Numeric strings are accepted the same way BlockStyles accepts them,
        // because file-owned config may store numbers as strings; they are
        // still coerced to a bounded integer plus a fixed unit.
        $this->assertSame(
            '[data-studio-path="public.landing.hero.subtitle"]{border-radius:8px;font-size:150%;}',
            \App\Support\StudioStyles::css([['path' => 'public.landing.hero.subtitle', 'radius' => '8', 'font_scale' => '150']])
        );

        // Bounds are inclusive, and the last row wins for the same path.
        $this->assertSame(
            '[data-studio-path="public.landing.hero.subtitle"]{padding:128px;inline-size:10%;}',
            \App\Support\StudioStyles::css([
                ['path' => 'public.landing.hero.subtitle', 'padding' => 4, 'width' => 100],
                ['path' => 'public.landing.hero.subtitle', 'padding' => 128, 'width' => 10],
            ])
        );

        // An addressable row with no usable style emits nothing rather than an
        // empty rule.
        $this->assertSame('', \App\Support\StudioStyles::css([['path' => 'public.landing.hero.subtitle']]));
    }

    public function test_override_list_refreshes_the_preview_by_reload(): void
    {
        // Override rows change the rendered markup (a per-element stylesheet),
        // so the live preview must classify the list as a full re-render and
        // must never mistake it for a swappable CSS token.
        $this->assertSame('reload', StudioSchema::liveMode('public.landing.overrides'));
    }
}
