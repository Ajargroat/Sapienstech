<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\WebsiteConfig;
use Illuminate\Support\Arr;

/**
 * Resolves the tenant-facing site config for the current request.
 *
 * Layer order, later wins:
 *
 *   1. config/theme.php               platform baseline
 *   2. config/archetypes/{name}.php   tokens AND structure as one bundle
 *   3. config/tenants/{slug}.php      per-tenant overrides, deploy-time, reviewable
 *   4. website_configs.layout_config  tenant-admin edits, runtime, no deploy
 *
 * then ThemeTokens::resolve() derives whatever the layers left null.
 *
 * Because the archetype sits *below* the tenant file, "the tenant's explicit
 * values beat the archetype" falls out of the merge order for free — no
 * precedence bookkeeping anywhere.
 *
 * Resolved trees are memoized per slug for the lifetime of the request, and the
 * class is bound as a *scoped* singleton so an Octane request can never read
 * another tenant's tree.
 */
class SiteConfig
{
    /** @var array<string, array> slug => resolved config tree */
    protected array $resolved = [];

    /** Top-most layer: runtime edits that beat every file and the DB. */
    protected array $runtime = [];

    /**
     * Per-user personalization layer, applied above $runtime.
     *
     * Kept separate from $runtime so the "just for me" theme can be replaced
     * wholesale on every request (ApplyPersonalTheme always writes it, empty
     * when the user has none) without clobbering the test/live-preview
     * overrides that share $runtime.
     */
    protected array $personal = [];

    /** @var array<string, array> config path + relative => file contents */
    protected static array $files = [];

    public function __construct(protected string $configPath) {}

    // =========================================================================
    // Public API
    // =========================================================================

    /** The fully resolved config for the current tenant. */
    public function all(): array
    {
        return $this->resolve(tenant());
    }

    /** Dotted lookup into the current tenant's resolved config. */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /** Resolve for an explicit tenant (used by `tenant:validate`). */
    public function resolve(?Tenant $tenant): array
    {
        $slug = $tenant?->slug ?? '__platform__';

        return $this->resolved[$slug] ??= $this->build($tenant);
    }

    /**
     * Layer an override on top of everything, for the current request only.
     *
     * This is the supported seam for tests, for a live archetype preview, and
     * for the future website-settings screen — anything that needs to change
     * the resolved config without writing a file or a DB row.
     */
    public function override(array $layer): static
    {
        $this->runtime  = Merge::structural($this->runtime, $layer);
        $this->resolved = [];

        return $this;
    }

    /**
     * Replace (not merge) the per-user personalization layer.
     *
     * Called on every authenticated request by ApplyPersonalTheme, passing []
     * when the user has no personal theme, so one user's layer can never leak
     * into the next request even when the app instance is reused.
     */
    public function personalize(array $layer): static
    {
        $this->personal = $layer;
        $this->resolved = [];

        return $this;
    }

    /** Drop memoized trees so the next read re-resolves. */
    public function flush(): void
    {
        $this->resolved = [];
        static::$files = [];
    }

    // =========================================================================
    // Layering
    // =========================================================================

    protected function build(?Tenant $tenant): array
    {
        $config = (array) config('theme', []);

        if (! $tenant) {
            return $this->finalize(Merge::structural(
                Merge::structural($config, $this->runtime),
                $this->personal
            ));
        }

        $tenantFile = $this->load("tenants/{$tenant->slug}");
        $database   = $this->databaseLayer($tenant);

        // The archetype can be named by any of the three layers below the
        // bundle itself, so it has to be decided before the bundle is applied.
        $archetype = Arr::get($database, 'theme.archetype')
            ?? Arr::get($tenantFile, 'theme.archetype')
            ?? Arr::get($config, 'theme.archetype');

        if ($archetype) {
            $config = Merge::structural($config, $this->load("archetypes/{$archetype}"));
        }

        $config = Merge::structural($config, $tenantFile);

        if ($database !== []) {
            $config = Merge::structural($config, $database);
        }

        if ($this->runtime !== []) {
            $config = Merge::structural($config, $this->runtime);
        }

        if ($this->personal !== []) {
            $config = Merge::structural($config, $this->personal);
        }

        Arr::set($config, 'theme.archetype', $archetype);

        return $this->finalize($config);
    }

    /** Merge the resolved theme block back into the config tree. */
    protected function finalize(array $config): array
    {
        $config['theme'] = ThemeTokens::resolve($config['theme'] ?? []);

        return $config;
    }

    // =========================================================================
    // Layers
    // =========================================================================

    /**
     * Per-tenant edits stored in the DB.
     *
     * `updated_at` is baked into the cache key, so saving the row invalidates
     * it without model listeners. The global tenant scope is skipped because
     * this resolves for an explicitly-passed tenant, which is not necessarily
     * the one bound to the container.
     */
    protected function databaseLayer(Tenant $tenant): array
    {
        $row = WebsiteConfig::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $row) {
            return [];
        }

        $key = self::dbLayerCacheKey($tenant->id, optional($row->updated_at)->getTimestamp());

        return cache()->rememberForever($key, static fn (): array => $row->toOverrideLayer());
    }

    /**
     * Cache key for a tenant's DB override layer. ConfigWriter forgets the
     * pre-save key after publishing: `updated_at` has second granularity, so
     * two saves within the same second would otherwise share one key and the
     * second would read the first's stale layer.
     */
    public static function dbLayerCacheKey(int $tenantId, ?int $timestamp): string
    {
        return "site:db:{$tenantId}:".($timestamp ?? 0);
    }

    /**
     * Load a config file relative to the config path.
     *
     * A plain require rather than the config repository, so the pipeline behaves
     * identically with and without `config:cache`, and a missing tenant file is
     * a silent no-op instead of an exception.
     */
    protected function load(string $relative): array
    {
        $cacheKey = $this->configPath.'|'.$relative;

        if (isset(static::$files[$cacheKey])) {
            return static::$files[$cacheKey];
        }

        $path = $this->configPath.DIRECTORY_SEPARATOR.$relative.'.php';

        return static::$files[$cacheKey] = is_file($path) ? (array) require $path : [];
    }
}
