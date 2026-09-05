<?php

use App\Support\SiteConfig;

if (! function_exists('site')) {
    /**
     * Tenant-resolved site config. Same dotted syntax as config(), but the
     * result is the current tenant's view of it: baseline merged with their
     * archetype, their file and their DB edits, with derived tokens filled in.
     *
     * Reads `theme.*` by default, so site('colors.primary') == the palette.
     */
    function site(?string $key = null, mixed $default = null): mixed
    {
        $site = app(SiteConfig::class);

        if ($key === null) {
            return $site->all();
        }

        // Accept both `theme.colors.x` and the shorthand `colors.x`.
        $resolved = $site->all();

        return data_get($resolved, $key, data_get($resolved['theme'] ?? [], $key, $default));
    }
}

if (! function_exists('site_override')) {
    /**
     * Layer a runtime override onto the resolved config, current request only.
     *
     * The supported seam for tests and for a live theme preview. Replaces the
     * old `config(['consultant.…' => x])` poking, which no longer reaches the
     * tenant resolver.
     */
    function site_override(array $layer): void
    {
        app(SiteConfig::class)->override($layer);
    }
}

if (! function_exists('tenant_asset')) {
    /**
     * URL for a tenant-owned asset, falling back to the shared public/ tree.
     *
     * Looks for public/tenants/{slug}/{path} first and falls back to {path}, so
     * tenants can be migrated to their own asset folder one image at a time
     * instead of all at once.
     */
    function tenant_asset(string $path): string
    {
        $slug  = tenant()?->slug;
        $local = $slug ? "tenants/{$slug}/".ltrim($path, '/') : null;

        if ($local && file_exists(public_path($local))) {
            return asset($local);
        }

        return asset($path);
    }
}

if (! function_exists('persian_digits')) {
    /**
     * Renders numerals in the active tenant's style.
     *
     * `theme.i18n.numerals` is 'fa', 'en', or 'auto'; 'auto' keeps the historic
     * behaviour of following the tenant locale, which is what every existing
     * caller relied on.
     */
    function persian_digits($value): string
    {
        $mode = site('i18n.numerals', 'auto');

        if ($mode === 'auto') {
            $mode = site('tenant.locale', config('app.locale', 'fa')) === 'fa' ? 'fa' : 'en';
        }

        return $mode === 'fa'
            ? strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                                      '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'])
            : (string) $value;
    }
}
