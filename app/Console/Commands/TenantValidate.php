<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\SiteConfig;
use App\Support\ThemeTokens;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

/**
 * Static checks a tenant config should pass before it ships.
 *
 * Without these, a typo in a tenant file becomes a silently blank section or an
 * unreadable palette, and the bug report lands on you rather than on the diff.
 */
class TenantValidate extends Command
{
    protected $signature = 'tenant:validate {slug? : Validate one tenant; omit for all}';

    protected $description = 'Check tenant configs for unknown keys, contrast failures, missing assets and missing section templates';

    /** Ratios below which we complain. 4.5 is WCAG AA for body text. */
    private const MIN_TEXT_CONTRAST = 4.5;
    private const MIN_LARGE_CONTRAST = 3.0;

    private int $problems = 0;

    public function handle(): int
    {
        $tenants = $this->argument('slug')
            ? Tenant::where('slug', $this->argument('slug'))->get()
            : Tenant::orderBy('slug')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants matched.');
            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->validateTenant($tenant);
        }

        $this->newLine();

        if ($this->problems === 0) {
            $this->components->info('All tenants valid.');
            return self::SUCCESS;
        }

        $this->components->error("{$this->problems} problem(s) found.");
        return self::FAILURE;
    }

    private function validateTenant(Tenant $tenant): void
    {
        $this->newLine();
        $this->line("<fg=cyan>{$tenant->slug}</> (id {$tenant->id})");

        $config = app(SiteConfig::class)->resolve($tenant);

        $this->checkUnknownKeys($tenant, $config);
        $this->checkContrast($config);
        $this->checkSections($config);
        $this->checkAssets($config, $tenant->slug);
    }

    // =========================================================================
    // Checks
    // =========================================================================

    /**
     * A key present in the tenant file but absent from the baseline is almost
     * always a typo, and it fails silently because nothing reads it.
     */
    private function checkUnknownKeys(Tenant $tenant, array $config): void
    {
        $path = config_path("tenants/{$tenant->slug}.php");

        if (! is_file($path)) {
            $this->line('  <fg=gray>no config file; inherits the baseline</>');
            return;
        }

        $baseline = (array) config('theme');
        $own      = (array) require $path;

        foreach ($this->leafPaths($own) as $key) {
            // The archetype marker is written by the resolver, and `theme.vars`
            // is generated output rather than input.
            if ($key === 'theme.archetype' || str_starts_with($key, 'theme.vars')) {
                continue;
            }

            // Arr::has rather than a flattened key set, so a list-valued path
            // like `public.landing.sections` counts as known.
            if (! Arr::has($baseline, $key)) {
                $this->problem("unknown config key: {$key}");
            }
        }
    }

    /** @return list<string> dotted paths of every leaf */
    private function leafPaths(array $array, string $prefix = ''): array
    {
        $paths = [];

        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $paths = array_merge($paths, $this->leafPaths($value, $path));
            } else {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function checkContrast(array $config): void
    {
        $c = $config['theme']['colors'] ?? [];

        $pairs = [
            ['text', 'background', self::MIN_TEXT_CONTRAST, 'body text on page'],
            ['text', 'surface',    self::MIN_TEXT_CONTRAST, 'body text on cards'],
            ['on_primary', 'primary', self::MIN_TEXT_CONTRAST, 'button label on primary'],
            ['text_muted', 'background', self::MIN_LARGE_CONTRAST, 'muted text on page'],
        ];

        foreach ($pairs as [$fore, $back, $min, $label]) {
            $ratio = ThemeTokens::contrastRatio($c[$fore] ?? null, $c[$back] ?? null);

            if ($ratio === null) {
                // Derived tokens are color-mix() expressions, not hex; a browser
                // resolves them and we cannot. Only flag genuinely missing tokens.
                if (($c[$fore] ?? null) === null || ($c[$back] ?? null) === null) {
                    $this->problem("missing colour token for '{$label}'");
                }
                continue;
            }

            if ($ratio < $min) {
                $this->problem(sprintf("low contrast (%.2f < %.1f) for %s: %s on %s",
                    $ratio, $min, $label, $c[$fore], $c[$back]));
            }
        }
    }

    /** Every section name must resolve to a template, and every variant must exist or fall back. */
    private function checkSections(array $config): void
    {
        $landing = $config['public']['landing'] ?? [];

        foreach ($landing['sections'] ?? [] as $section) {
            if (! is_file($this->viewPath("Public/sections/{$section}.blade.php"))) {
                $this->problem("section '{$section}' has no template — it will render nothing");
                continue;
            }

            $variant = $landing[$section]['variant'] ?? ($landing[$section]['layout'] ?? null);

            if ($variant && ! is_file($this->viewPath("Public/sections/{$section}/{$variant}.blade.php"))) {
                $this->line("  <fg=yellow>note</> section '{$section}' variant '{$variant}' falls back to default");
            }
        }
    }

    private function checkAssets(array $config, string $slug): void
    {
        $paths = [];

        foreach (Arr::dot($config['public'] ?? []) as $key => $value) {
            if (is_string($value) && preg_match('/\.(png|jpe?g|svg|webp|gif)$/i', $value)) {
                $paths[] = $value;
            }
        }

        if ($src = $config['theme']['brand']['src'] ?? null) {
            $paths[] = $src;
        }

        foreach (array_unique($paths) as $path) {
            $local = "tenants/{$slug}/{$path}";

            if (! is_file(public_path($local)) && ! is_file(public_path($path))) {
                $this->problem("missing asset: public/{$path} (or public/{$local})");
            }
        }
    }

    private function viewPath(string $relative): string
    {
        return resource_path('views/'.str_replace('/', DIRECTORY_SEPARATOR, $relative));
    }

    private function problem(string $message): void
    {
        $this->problems++;
        $this->line("  <fg=red>✗</> {$message}");
    }
}
