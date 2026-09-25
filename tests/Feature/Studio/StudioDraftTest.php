<?php

namespace Tests\Feature\Studio;

use App\Http\Middleware\ApplyPersonalTheme;
use App\Models\Domain;
use App\Models\StudioDraft;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteConfig;
use App\Support\StudioSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 8: THE single Theme Studio draft (Prompts §6).
 *
 * The draft is a checkpoint of the editor FORM — field name => submitted
 * values — and nothing more: the JSON endpoints carry it around, they never
 * merge it into config. Restoring is a client re-submit of the payload to
 * studio.save with scope=preview, so these tests pin both halves: the store
 * is isolated per tenant+user and structurally validated (reserved names
 * stripped), and a restore touches ONLY the session working layer —
 * website_configs and the personal layer stay byte-identical. Draft is not
 * publish.
 */
class StudioDraftTest extends TestCase
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

    /** The shape the browser sends: bracket field name => list of values. */
    private function checkpoint(): array
    {
        return [
            'theme[colors][primary]' => ['#aabbcc'],
            'public[canvas][nodes]' => [json_encode([
                'public.landing.hero.eyebrow' => ['props' => ['color' => '#112233']],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            'public[landing][sections][]' => ['hero', 'cta', 'stats'],
        ];
    }

    /**
     * Nested form data => the flat bracket-name map the browser's FormData
     * carries, matching what studio-draft.js snapshots. Nulls are skipped:
     * an empty text input posts '' but a file input posts nothing, and the
     * resolved layer only speaks null — replaying '' into a file rule would
     * fail where the real form would send nothing at all.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<int, string>>
     */
    private function toBracket(array $data, string $prefix = ''): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix.'['.$key.']';

            if ($value === null) {
                continue;
            }

            if (! is_array($value)) {
                // Mirror the browser snapshot: an empty value is "absent"
                // (the request layer would null it out anyway).
                if ((string) $value === '') {
                    continue;
                }

                $out[$name] = [(string) $value];

                continue;
            }

            if (array_is_list($value)) {
                if ($value === []) {
                    $out[$name.'[]'] = [];

                    continue;
                }

                if (! is_array($value[0])) {
                    $out[$name.'[]'] = array_map(static fn ($item) => (string) $item, $value);

                    continue;
                }

                foreach ($value as $index => $row) {
                    $out += $this->toBracket((array) $row, $name.'['.$index.']');
                }

                continue;
            }

            $out += $this->toBracket($value, $name);
        }

        return $out;
    }

    /**
     * Bracket names decoded the way PHP parses a real form post — the same
     * hidden inputs studio-draft.js builds on restore (scope last, so the
     * reserved key is always present exactly once).
     *
     * @param  array<string, array<int, string>>  $payload
     * @return array<string, mixed>
     */
    private function replayInput(array $payload): array
    {
        $input = [];

        foreach ($payload as $name => $values) {
            // `field[inner][]` lists keep their bracket suffix out of the
            // path; `]` unbrackets to nothing (a dot here would leave an
            // empty segment and nest the value under key '').
            $isList = str_ends_with($name, '[]');
            $path = str_replace(['][', '[', ']'], ['.', '.', ''], $isList ? substr($name, 0, -2) : $name);
            $value = $isList || count($values) !== 1
                ? array_values($values)
                : $values[0];

            data_set($input, $path, $value);
        }

        $input['scope'] = 'preview';

        return $input;
    }

    /**
     * A full form submission: every schema field at its resolved value, with
     * dotted-path overrides applied. The canvas field is special-cased
     * because its value rides as a JSON string, not a dotted scalar.
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

    public function test_show_reports_no_draft_then_the_stored_checkpoint(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant);

        $this->actingAs($admin)
            ->getJson("http://{$host}/studio/draft")
            ->assertOk()
            ->assertJson(['draft' => null]);

        $created = $this->postJson("http://{$host}/studio/draft", [
            'name' => 'بازطراحی هدر',
            'payload' => $this->checkpoint(),
        ])->assertCreated();

        $this->assertSame('بازطراحی هدر', $created->json('draft.name'));
        $this->assertSame(['#aabbcc'], $created->json('draft.payload')['theme[colors][primary]']);
        $this->assertNotNull($created->json('draft.updated_at'));

        $this->getJson("http://{$host}/studio/draft")
            ->assertOk()
            ->assertJsonPath('draft.name', 'بازطراحی هدر');

        $this->assertSame(1, StudioDraft::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_only_one_draft_may_exist(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant);

        $this->actingAs($admin)->postJson("http://{$host}/studio/draft", [
            'name' => 'اولین',
            'payload' => $this->checkpoint(),
        ])->assertCreated();

        $this->actingAs($admin)->postJson("http://{$host}/studio/draft", [
            'name' => 'دومین',
            'payload' => $this->checkpoint(),
        ])->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['name']]);

        $this->assertSame(1, StudioDraft::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame('اولین', StudioDraft::query()->first()->name);
    }

    public function test_update_renames_or_replaces_the_payload_independently(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant);

        $this->actingAs($admin)->postJson("http://{$host}/studio/draft", [
            'name' => 'نام اول',
            'payload' => $this->checkpoint(),
        ])->assertCreated();

        // Rename keeps the checkpoint.
        $renamed = $this->actingAs($admin)->putJson("http://{$host}/studio/draft", ['name' => 'نام تازه'])->assertOk();
        $this->assertSame('نام تازه', $renamed->json('draft.name'));
        $this->assertSame(['#aabbcc'], $renamed->json('draft.payload')['theme[colors][primary]']);

        // Overwrite keeps the name.
        $fresh = ['theme[colors][primary]' => ['#010203']];
        $updated = $this->actingAs($admin)->putJson("http://{$host}/studio/draft", ['payload' => $fresh])->assertOk();
        $this->assertSame('نام تازه', $updated->json('draft.name'));
        $this->assertSame(['#010203'], $updated->json('draft.payload')['theme[colors][primary]']);

        // An empty update is not a thing.
        $this->actingAs($admin)->putJson("http://{$host}/studio/draft", [])->assertStatus(422);

        // Update without a draft is a 404, not a silent create.
        StudioDraft::query()->delete();
        $this->actingAs($admin)->putJson("http://{$host}/studio/draft", ['name' => 'x'])->assertStatus(404);
    }

    public function test_destroy_clears_the_checkpoint_idempotently(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant);

        $this->actingAs($admin)->postJson("http://{$host}/studio/draft", [
            'name' => 'برای حذف',
            'payload' => $this->checkpoint(),
        ])->assertCreated();

        $this->actingAs($admin)->deleteJson("http://{$host}/studio/draft")->assertOk();
        $this->actingAs($admin)->getJson("http://{$host}/studio/draft")->assertOk()->assertJson(['draft' => null]);
        $this->actingAs($admin)->deleteJson("http://{$host}/studio/draft")->assertOk();
    }

    public function test_reserved_payload_keys_are_stripped_on_store(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant);

        $crafted = $this->checkpoint();
        $crafted['_token'] = ['forged-csrf'];
        $crafted['scope'] = ['everyone'];
        $crafted['_method'] = ['DELETE'];

        $this->actingAs($admin)->postJson("http://{$host}/studio/draft", [
            'name' => 'با کلیدهای رزرو',
            'payload' => $crafted,
        ])->assertCreated();

        $stored = StudioDraft::query()->first()->payload;

        $this->assertArrayNotHasKey('_token', $stored);
        $this->assertArrayNotHasKey('scope', $stored);
        $this->assertArrayNotHasKey('_method', $stored);
        $this->assertSame(['#aabbcc'], $stored['theme[colors][primary]']);
    }

    public function test_hostile_payload_shapes_are_rejected(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant);

        // Payload must be the map, not a scalar.
        $this->actingAs($admin)->postJson("http://{$host}/studio/draft", [
            'name' => 'بدون نقشه',
            'payload' => 'not-a-map',
        ])->assertStatus(422);

        // Each field holds a list of values, not a bare string.
        $this->actingAs($admin)->postJson("http://{$host}/studio/draft", [
            'name' => 'مقدار تنها',
            'payload' => ['theme[colors][primary]' => '#aabbcc'],
        ])->assertStatus(422);

        // A name is mandatory on create.
        $this->actingAs($admin)->postJson("http://{$host}/studio/draft", [
            'payload' => $this->checkpoint(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertSame(0, StudioDraft::query()->count());
    }

    public function test_drafts_are_isolated_per_tenant_and_per_user(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain();
        [$tenantB, $hostB] = $this->tenantWithDomain();
        $adminA = $this->userFor($tenantA);
        $otherA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'tenant_admin']);

        $this->actingAs($adminA)
            ->postJson("http://{$hostA}/studio/draft", [
                'name' => 'مخصوص A',
                'payload' => $this->checkpoint(),
            ])->assertCreated();

        // Another user on the same tenant starts empty (unique is per user).
        $this->actingAs($otherA)
            ->getJson("http://{$hostA}/studio/draft")
            ->assertOk()
            ->assertJson(['draft' => null]);

        // A different tenant cannot see it either.
        $adminB = $this->userFor($tenantB);
        $this->actingAs($adminB)
            ->getJson("http://{$hostB}/studio/draft")
            ->assertOk()
            ->assertJson(['draft' => null]);

        // The global tenant scope follows the CURRENT request tenant — ask
        // for the raw rows so switching hosts above does not hide them.
        $this->assertSame(1, StudioDraft::withoutGlobalScopes()->count());
        $this->assertSame($tenantA->id, StudioDraft::withoutGlobalScopes()->first()->tenant_id);
        $this->assertSame($adminA->id, StudioDraft::withoutGlobalScopes()->first()->user_id);
    }

    public function test_restoring_a_draft_writes_the_working_layer_and_never_publishes(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $admin = $this->userFor($tenant);

        // Capture the full form exactly as the browser would (the draft only
        // replays what the form actually carries — every required field).
        $snapshot = $this->toBracket($this->payload(['theme.colors.primary' => '#aabbcc']));

        $bad = [];
        foreach ($snapshot as $key => $values) {
            foreach ($values as $index => $value) {
                if (! is_string($value)) {
                    $bad[$key][$index] = gettype($value).':'.json_encode($value);
                }
            }
        }
        $this->assertSame([], $bad, json_encode($bad, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->actingAs($admin)
            ->postJson("http://{$host}/studio/draft", [
                'name' => 'برای بازگردانی',
                'payload' => $snapshot,
            ])->assertCreated();

        $before = WebsiteConfig::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->first();
        $preferencesBefore = $admin->fresh()->preferences ?? [];

        // The client's restore: replay the stored payload to the ordinary
        // save endpoint with scope forced to preview — the exact POST the
        // draft module builds (hidden inputs, live CSRF, working layer only).
        $this->post("http://{$host}/studio", $this->replayInput(
            StudioDraft::query()->first()->payload
        ))->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas(ApplyPersonalTheme::previewSessionKey());

        // Working layer: the preview session carries the restored value.
        $layer = session(ApplyPersonalTheme::previewSessionKey());
        $this->assertSame('#aabbcc', data_get($layer, 'theme.colors.primary'));

        // Published layers: byte-identical — nothing was written.
        $after = WebsiteConfig::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->first();
        $this->assertSame(
            $before?->getAttributes() ?? [],
            $after?->getAttributes() ?? [],
        );
        $this->assertNull(data_get($after?->layout_config, 'theme.colors.primary'));
        $this->assertSame($preferencesBefore, $admin->fresh()->preferences ?? []);
    }
}
