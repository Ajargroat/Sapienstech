<?php

namespace Tests\Feature\Studio;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteConfig;
use App\Support\StudioSchema;
use App\Support\StudioStyles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 1 of the canvas rebuild: the `public.canvas.nodes` document model.
 *
 * The canvas store is a map keyed by element id (data-studio-path). Because a
 * node's id contains dots it cannot ride a dotted path, so the whole map is
 * validated wholesale at the security boundary — StudioStyles::cleanNodes —
 * exactly as the legacy `public.landing.overrides` list is. These tests pin
 * that boundary shut: an unknown id, a non-whitelisted id, a hostile value, or
 * a locked node must never reach the emitted stylesheet or the stored layer.
 */
class StudioCanvasTest extends TestCase
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

    private function userFor(Tenant $tenant, string $role = 'tenant_admin'): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
    }

    // =========================================================================
    // The security boundary: cleanNodes()
    // =========================================================================

    public function test_unknown_element_ids_are_dropped(): void
    {
        $this->assertNull(StudioStyles::cleanNodes([
            'public.landing.hero.does_not_exist' => ['props' => ['color' => '#123456']],
        ]));

        // A real path makes the same map non-empty; the bogus sibling is gone.
        $clean = StudioStyles::cleanNodes([
            'public.landing.hero.does_not_exist' => ['props' => ['color' => '#123456']],
            'public.landing.hero.eyebrow' => ['props' => ['color' => '#123456']],
        ]);

        $this->assertSame(['public.landing.hero.eyebrow'], array_keys($clean));
    }

    public function test_non_whitelisted_ids_are_rejected(): void
    {
        foreach ([
            'public.landing',                 // page root names no element
            'public.nav',                     // addressed at least one segment deep
            'theme.colors.text',              // not a public path at all
            'public.landing.hero.eyebrow<x>', // charset is [a-z0-9_.]
            'public.landing.hero.eyebrow"',   // selector-breakout attempt
            'public.blog.show.title',         // root is not landing|nav|footer
            'Public.landing.hero.eyebrow',    // case matters
        ] as $path) {
            $this->assertFalse(StudioStyles::validNodeId($path), $path);
            $this->assertNull(StudioStyles::cleanNodes([$path => ['props' => ['color' => '#123456']]]), $path);
        }

        // Leaf fields, containers, list rows and section-root markers pass.
        foreach ([
            'public.landing.hero.eyebrow', 'public.landing.hero.buttons',
            'public.landing.hero.buttons.0', 'public.nav.cta.label',
        ] as $path) {
            $this->assertTrue(StudioStyles::validNodeId($path), $path);
        }
    }

    public function test_hostile_values_are_dropped_at_the_boundary(): void
    {
        $clean = StudioStyles::cleanNodes([
            'public.landing.hero.eyebrow' => [
                'props' => [
                    'color' => 'red;}html{display:none',   // needs #rrggbb
                    'background' => '#GGGGGG',             // not hex
                    'radius' => '8px',                     // unit is added, not given
                    'padding' => 999,                      // out of range
                    'width' => 5,                          // below min
                    'font_scale' => '150px',               // not a bare integer
                    'align' => 'justify-all',              // not a choice
                ],
                'states' => ['hover' => ['color' => '#123456']],
                'breakpoints' => ['watch' => ['padding' => 8]], // unknown device
            ],
        ]);

        // Only the whitelisted state survived; every hostile prop was dropped.
        $this->assertSame(['states' => ['hover' => ['color' => '#123456']], 'props' => [], 'breakpoints' => [], 'flags' => []], $this->normalizeNode($clean['public.landing.hero.eyebrow']));
        $this->assertSame(
            '[data-studio-path="public.landing.hero.eyebrow"]:hover{color:#123456;}',
            StudioStyles::nodesCss($clean)
        );
    }

    public function test_unknown_node_keys_and_flags_do_not_survive(): void
    {
        $clean = StudioStyles::cleanNodes([
            'public.landing.hero.eyebrow' => [
                'props' => ['color' => '#123456', 'mix_blend_mode' => 'multiply', 'z_index' => 5],
                'flags' => ['locked' => true, 'admin' => true, 'deleted' => true],
                'nonsense' => ['x' => 1],
            ],
        ]);

        $node = $clean['public.landing.hero.eyebrow'];
        $this->assertSame(['color' => '#123456'], $node['props']);
        $this->assertSame(['locked' => true], $node['flags']);
        $this->assertArrayNotHasKey('nonsense', $node);
    }

    // =========================================================================
    // The emitter: nodesCss()
    // =========================================================================

    public function test_local_state_and_breakpoint_rules_are_ordered_narrow_to_wide(): void
    {
        $css = StudioStyles::nodesCss([
            'public.landing.hero.eyebrow' => [
                'props' => ['color' => '#111111'],
                'states' => ['hover' => ['color' => '#222222'], 'focus_visible' => ['color' => '#333333']],
                'breakpoints' => [
                    'mobile' => ['font_scale' => 150],
                    'tablet' => ['font_scale' => 175],
                    'desktop' => ['font_scale' => 200],
                ],
            ],
        ]);

        $this->assertSame(
            '[data-studio-path="public.landing.hero.eyebrow"]{color:#111111;}'
            .'[data-studio-path="public.landing.hero.eyebrow"]:hover{color:#222222;}'
            .'[data-studio-path="public.landing.hero.eyebrow"]:focus-visible{color:#333333;}'
            .'@media (max-width:640px){[data-studio-path="public.landing.hero.eyebrow"]{font-size:150%;}}'
            .'@media (min-width:641px) and (max-width:1024px){[data-studio-path="public.landing.hero.eyebrow"]{font-size:175%;}}'
            .'@media (min-width:1025px){[data-studio-path="public.landing.hero.eyebrow"]{font-size:200%;}}',
            $css
        );
    }

    public function test_hidden_nodes_are_out_of_flow_and_recoverable(): void
    {
        // Hide emits display:none — never an opacity/visibility counterfeit —
        // so the element leaves the flow and siblings close the gap.
        $css = StudioStyles::nodesCss([
            'public.landing.cta.heading' => ['flags' => ['hidden' => true]],
        ]);

        $this->assertSame('[data-studio-path="public.landing.cta.heading"]{display:none;}', $css);
        $this->assertStringNotContainsString('opacity', $css);
        $this->assertStringNotContainsString('visibility', $css);
    }

    public function test_a_hidden_node_emits_nothing_but_display_none(): void
    {
        // Below a hidden node every state/breakpoint value is unreachable, so
        // they are dropped rather than emitted into a subtree that is gone.
        $css = StudioStyles::nodesCss([
            'public.landing.cta.heading' => [
                'flags' => ['hidden' => true],
                'props' => ['color' => '#123456'],
                'states' => ['hover' => ['color' => '#654321']],
                'breakpoints' => ['mobile' => ['radius' => 8]],
            ],
        ]);

        $this->assertSame('[data-studio-path="public.landing.cta.heading"]{display:none;}', $css);
    }

    public function test_locked_nodes_emit_no_css(): void
    {
        // Lock is editor-only: a locked element stays visible, part of the real
        // website, and fully interactive there, so it must paint nothing.
        $this->assertSame('', StudioStyles::nodesCss([
            'public.landing.hero.eyebrow' => ['flags' => ['locked' => true]],
        ]));

        // A locked node with real styles still paints them; lock adds nothing.
        $this->assertSame(
            '[data-studio-path="public.landing.hero.eyebrow"]{color:#123456;}',
            StudioStyles::nodesCss([
                'public.landing.hero.eyebrow' => ['flags' => ['locked' => true], 'props' => ['color' => '#123456']],
            ])
        );
    }

    // =========================================================================
    // Persistence: the request -> writer path
    // =========================================================================

    public function test_a_canvas_submission_persists_clean_and_renders(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload([
            'public.canvas.nodes' => json_encode([
                'public.landing.hero.eyebrow' => [
                    'props' => ['color' => '#112233'],
                    'states' => ['hover' => ['color' => '#AABBCC']],
                    'breakpoints' => ['mobile' => ['font_scale' => 150]],
                    'flags' => ['locked' => true],
                ],
                'public.landing.cta.heading' => ['flags' => ['hidden' => true]],
                // Hostile rows ride along and must not reach the stored layer.
                'public.landing.hero.made_up' => ['props' => ['color' => '#123456']],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/studio", $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $stored = data_get(
            WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config,
            'public.canvas.nodes'
        );

        $this->assertSame(['public.landing.hero.eyebrow', 'public.landing.cta.heading'], array_keys($stored));

        $this->get("http://{$host}/")->assertOk()
            ->assertSee('id="lp-element-overrides"', false)
            ->assertSee('[data-studio-path="public.landing.hero.eyebrow"]{color:#112233;}', false)
            ->assertSee('@media (max-width:640px){[data-studio-path="public.landing.hero.eyebrow"]{font-size:150%;}}', false)
            ->assertSee('[data-studio-path="public.landing.cta.heading"]{display:none;}', false)
            ->assertDontSee('made_up', false);
    }

    public function test_capability_keys_persist_and_emit(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload([
            'public.canvas.nodes' => json_encode([
                'public.landing.hero.buttons' => [
                    'props' => [
                        'display' => 'flex',
                        'justify_content' => 'center',
                        'gap' => 16,
                        'margin' => 24,
                        'height' => 'auto',
                        'font_weight' => '700',
                        'line_height' => 150,
                        'text_transform' => 'uppercase',
                        'border_width' => 2,
                        'border_style' => 'solid',
                        'border_color' => '#112233',
                        'outline_width' => 1,
                        'outline_style' => 'dotted',
                        'box_shadow' => '0 4px 12px 0 #000000',
                        'opacity' => 55,
                        'filter' => 'blur(4px)',
                        'transform' => 'translate(10px, -4px) rotate(3deg)',
                        'transition' => 'all 0.2s ease',
                        'top' => -12,
                        'left' => 40,
                        'order' => -2,
                        'grid_column' => '1 / span 2',
                        'grid_row' => '3',
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/studio", $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $stored = data_get(
            WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config,
            'public.canvas.nodes'
        );
        $props = $stored['public.landing.hero.buttons']['props'];
        $this->assertSame('700', $props['font_weight']);
        $this->assertSame(55, $props['opacity']);
        $this->assertSame('auto', $props['height']);

        $css = StudioStyles::nodesCss($stored);
        foreach ([
            'display:flex;',
            'justify-content:center;',
            'gap:16px;',
            'margin:24px;',
            'block-size:auto;',
            'font-weight:700;',
            'line-height:150%;',
            'text-transform:uppercase;',
            'border-width:2px;',
            'border-style:solid;',
            'border-color:#112233;',
            'outline-width:1px;',
            'outline-style:dotted;',
            'box-shadow:0 4px 12px 0 #000000;',
            'opacity:0.55;',
            'filter:blur(4px);',
            'transform:translate(10px, -4px) rotate(3deg);',
            'transition:all 0.2s ease;',
            'top:-12px;',
            'left:40px;',
            'order:-2;',
            'grid-column:1 / span 2;',
            'grid-row:3;',
        ] as $declaration) {
            $this->assertStringContainsString($declaration, $css);
        }
    }

    public function test_hostile_capability_values_are_dropped(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload([
            'public.canvas.nodes' => json_encode([
                'public.landing.hero.buttons' => [
                    'props' => [
                        'transform' => '}</style><script>alert(1)</script>',
                        'box_shadow' => 'expression(alert(1))',
                        'filter' => 'url(javascript:alert(1))',
                        'height' => '10px;position:fixed',
                        'display' => 'banana',
                        'transition' => 'all 0s',
                        'font_weight' => 'bold',
                        'opacity' => 150,
                        'gap' => -4,
                        'top' => 'expression(1)',
                        'left' => 99999,
                        'order' => 'abc',
                        'grid_column' => '1; background:red',
                        'grid_row' => '1 / 2 / 3',
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/studio", $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $stored = data_get(
            WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config,
            'public.canvas.nodes'
        );

        // Every hostile or unknown-shaped value is gone at the boundary; a
        // node left with nothing is forgotten entirely.
        $this->assertNull($stored);

        $html = $this->get("http://{$host}/")->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('expression(alert(1))', $html);
    }

    public function test_token_references_persist_and_emit_var_shorthands(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload([
            'public.canvas.nodes' => json_encode([
                'public.landing.hero.eyebrow' => [
                    'props' => [
                        'color' => ['token' => 'colors.text'],
                        'background' => ['token' => 'colors.primary', 'sneaky' => 'x'],
                        'radius' => ['token' => 'shape.radius_md'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/studio", $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $stored = data_get(
            WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config,
            'public.canvas.nodes'
        );
        // Extra keys are projected away; the reference itself round-trips.
        // (Order follows the KEYS map, not the submission.)
        $this->assertSame(
            [
                'background' => ['token' => 'colors.primary'],
                'color' => ['token' => 'colors.text'],
                'radius' => ['token' => 'shape.radius_md'],
            ],
            $stored['public.landing.hero.eyebrow']['props']
        );

        $css = StudioStyles::nodesCss($stored);
        $this->assertStringContainsString('color:var(--c-text);', $css);
        $this->assertStringContainsString('background:var(--c-primary);', $css);
        $this->assertStringContainsString('border-radius:var(--radius-md);', $css);
        $this->assertStringNotContainsString('sneaky', $css);
    }

    public function test_unknown_token_references_are_dropped(): void
    {
        $clean = StudioStyles::cleanNodes([
            'public.landing.hero.eyebrow' => [
                'props' => [
                    'color' => ['token' => 'colors.not_a_real_token'],
                    'background' => ['token' => 123],
                    'radius' => ['token' => 'colors.primary', 'extra' => true],
                ],
            ],
        ]);

        // Unknown names and non-strings are dropped; a valid token with junk
        // siblings still projects down to the bare reference.
        $this->assertSame(
            ['radius' => ['token' => 'colors.primary']],
            $clean['public.landing.hero.eyebrow']['props']
        );
    }

    public function test_a_locked_button_stays_clickable_on_the_public_page(): void
    {
        // Lock is editor-only. The explicit page-level pin: a locked button
        // must stay in the markup, free of any stylesheet rule or attribute
        // that could hide, disable or unlink it, exactly as before the lock.
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $payload = $this->payload([
            'public.canvas.nodes' => json_encode([
                'public.landing.hero.buttons' => ['flags' => ['locked' => true]],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $payload['scope'] = 'everyone';

        $this->actingAs($admin)
            ->post("http://{$host}/studio", $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $html = $this->get("http://{$host}/")->assertOk()->getContent();

        // The locked container and its link are still in the markup.
        $this->assertStringContainsString('data-studio-path="public.landing.hero.buttons"', $html);
        $this->assertMatchesRegularExpression('/<a\b[^>]*data-studio-path="public\.landing\.hero\.buttons\.\d+"/', $html);

        // No inline stylesheet on the page targets the locked path: lock emits
        // nothing, so nothing can reach the element through the cascade.
        preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $html, $blocks);
        foreach ($blocks[1] as $css) {
            $this->assertStringNotContainsString('public.landing.hero.buttons', $css);
        }

        // The link itself carries no attribute that would block activation.
        preg_match('/<a\b[^>]*data-studio-path="public\.landing\.hero\.buttons\.\d+"[^>]*>/', $html, $link);
        $this->assertNotEmpty($link[0]);
        $this->assertStringContainsString('href=', $link[0]);
        foreach (['disabled', 'tabindex="-1"', 'aria-disabled'] as $blocked) {
            $this->assertStringNotContainsString($blocked, $link[0]);
        }
    }

    public function test_clearing_the_canvas_forgets_the_whole_map(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant, 'tenant_admin');

        $first = $this->payload([
            'public.canvas.nodes' => json_encode(['public.landing.hero.eyebrow' => ['props' => ['color' => '#112233']]]),
        ]);
        $first['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/studio", $first)->assertRedirect();

        $this->assertSame(
            ['public.landing.hero.eyebrow'],
            array_keys(data_get(
                WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config,
                'public.canvas.nodes'
            ))
        );

        // An emptied canvas means "forget": null, so the public page falls back
        // to the template look and the layer stays a true sparse diff.
        $empty = $this->payload(['public.canvas.nodes' => '']);
        $empty['scope'] = 'everyone';
        $this->actingAs($admin)->post("http://{$host}/studio", $empty)->assertRedirect();

        $this->assertNull(data_get(
            WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->layout_config,
            'public.canvas.nodes'
        ));

        $this->get("http://{$host}/")->assertOk()->assertDontSee('id="lp-element-overrides"', false);
    }

    /**
     * A full form submission: every schema field at its resolved value, with
     * dotted-path overrides applied. The canvas field is special-cased because
     * its value is carried as a JSON string, not a dotted scalar.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $resolved = site();
        $input = [];

        foreach (array_keys(StudioSchema::rules()) as $path) {
            $field = StudioSchema::field($path);
            $value = data_get($resolved, $path);

            if (($field['control'] ?? '') === 'toggle') {
                $value = $value ? '1' : '0';
            }

            if (($field['control'] ?? '') === 'canvas') {
                $value = $value ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
            }

            data_set($input, $path, $value);
        }

        foreach ($overrides as $path => $value) {
            data_set($input, $path, $value);
        }

        return $input;
    }

    /**
     * Compare a clean node ignoring key presence (cleanNodes always sets all
     * four keys, but a test that builds its own expectation may omit one).
     */
    private function normalizeNode(array $node): array
    {
        return [
            'states' => $node['states'] ?? [],
            'props' => $node['props'] ?? [],
            'breakpoints' => $node['breakpoints'] ?? [],
            'flags' => $node['flags'] ?? [],
        ];
    }
}
