<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteConfig;
use App\Support\LandingLint;
use App\Support\SiteConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Scaffolds a new tenant end to end.
 *
 * Without this, "add a tenant" is a seven-step ritual across three tables, two
 * files and one directory — and the 10-minute domain cache makes step seven look
 * like it didn't work.
 */
class TenantMake extends Command
{
    protected $signature = 'tenant:make
                            {name : Display name shown to visitors}
                            {slug : URL-safe identifier, used as the config and asset folder name}
                            {--domain= : Primary domain, e.g. moeinacademy.test}
                            {--archetype=aurora_glass : One of config/archetypes/*.php}
                            {--admin-email= : Optional tenant_admin login to create}
                            {--force : Overwrite an existing tenant config file}';

    protected $description = 'Create a tenant, its primary domain, its config file, its bespoke landing surface and its asset folder';

    /**
     * Characters a slug may not contain. `:` is the important one: it is legal in
     * a URL and a Linux filename but illegal on Windows, where it silently turns
     * `config/tenants/foo:bar.php` into an alternate data stream on `foo`.
     */
    private const INVALID_SLUG_CHARS = ':*?"<>|/\\';

    public function handle(): int
    {
        $slug = Str::slug((string) $this->argument('slug'));
        $name = (string) $this->argument('name');
        $arche = (string) $this->option('archetype');

        if ($slug === '') {
            $this->error('The slug resolved to an empty string.');

            return self::FAILURE;
        }

        if (strpbrk($slug, self::INVALID_SLUG_CHARS) !== false) {
            $this->error('Slug contains a character that is unsafe on Windows filesystems: '.self::INVALID_SLUG_CHARS);

            return self::FAILURE;
        }

        if (! is_file(config_path("archetypes/{$arche}.php"))) {
            $this->error("Unknown archetype '{$arche}'. Available: ".implode(', ', $this->archetypes()));

            return self::FAILURE;
        }

        if (Tenant::where('slug', $slug)->exists()) {
            $this->error("A tenant with slug '{$slug}' already exists.");

            return self::FAILURE;
        }

        $configPath = config_path("tenants/{$slug}.php");

        if (is_file($configPath) && ! $this->option('force')) {
            $this->error("config/tenants/{$slug}.php already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        // ---- database ------------------------------------------------------
        $tenant = DB::transaction(function () use ($name, $slug) {
            $tenant = Tenant::create(['name' => $name, 'slug' => $slug, 'status' => 'active']);

            WebsiteConfig::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'archetype' => null,
            ]);

            return $tenant;
        });

        $this->components->info("Tenant '{$name}' created (id {$tenant->id}).");

        // ---- config file ---------------------------------------------------
        if ($this->writeConfigFile($configPath, $slug, $name, $arche) === self::FAILURE) {
            return self::FAILURE;
        }

        // ---- bespoke surface ----------------------------------------------
        // A new tenant is a *new-landing* tenant from its first request: the
        // scaffold is the same bespoke surface system the shipped tenants use
        // (resources/landings/{slug}/), not the legacy shared skeleton. Both
        // files are copied from the checked-in stubs, linted, and published.
        if ($this->writeSurface($slug) === self::FAILURE) {
            return self::FAILURE;
        }

        // ---- domain --------------------------------------------------------
        if ($domain = $this->option('domain')) {
            $this->createDomain($tenant, (string) $domain);
        }

        // ---- assets --------------------------------------------------------
        $assetDir = public_path("tenants/{$slug}/images");
        if (! is_dir($assetDir) && ! mkdir($assetDir, 0755, true) && ! is_dir($assetDir)) {
            $this->warn("Could not create {$assetDir} — create it manually before uploading tenant images.");
        }

        // ---- admin user ----------------------------------------------------
        if ($email = $this->option('admin-email')) {
            $this->createAdmin($tenant, (string) $email);
        }

        $this->nextSteps($slug);

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function archetypes(): array
    {
        return array_map(
            static fn (string $p): string => basename($p, '.php'),
            glob(config_path('archetypes').'/*.php') ?: []
        );
    }

    /**
     * Write the tenant config file and refuse to keep it if it does not parse.
     *
     * Laravel auto-loads every PHP file under config/, so a malformed tenant
     * file is not a soft failure — it takes down the whole app on the next
     * boot. Lint before declaring success.
     */
    private function writeConfigFile(string $path, string $slug, string $name, string $archetype): int
    {
        $stub = <<<PHP
        <?php

        /*
        |--------------------------------------------------------------------------
        | Tenant: {$slug}
        |--------------------------------------------------------------------------
        |
        | Only the keys that differ from config/theme.php belong here; anything
        | unset inherits the baseline. Add the tenant's own advisor, numbers,
        | articles and footer copy under `public` as it becomes available.
        |
        */

        return [

            'tenant' => [
                'name'       => {$this->phpString($name)},
                'short_name' => {$this->phpString($name)},
            ],

            'theme' => [
                'archetype' => {$this->phpString($archetype)},
            ],

            'public' => [
                'landing' => [
                    'hero' => [
                        'title_line2' => {$this->phpString($name)},
                    ],
                ],
            ],

        ];

        PHP;

        // The heredoc above is indented; PHP 7.3+ strips the closing marker's
        // indentation from every line, so the file lands at column 0.
        file_put_contents($path, $stub."\n");

        exec('php -l '.escapeshellarg($path).' 2>&1', $output, $status);

        if ($status !== 0) {
            unlink($path);
            $this->error('Generated config/tenants/'.$slug.'.php did not parse and was removed:');
            $this->line(implode("\n", $output));

            return self::FAILURE;
        }

        $this->components->info("Wrote config/tenants/{$slug}.php");

        app(SiteConfig::class)->flush();

        return self::SUCCESS;
    }

    /**
     * Scaffold the tenant's bespoke landing surface.
     *
     * The files are checked-in stubs (resources/stubs/), so the generated page
     * is exactly the page the factory ships and is linted here before it is
     * published. A surface that fails the contract is written disabled rather
     * than pretending to be live: LandingSurface then falls back to the shared
     * page, which is the fail-closed behaviour the resolver already provides.
     */
    private function writeSurface(string $slug): int
    {
        $dir = resource_path('landings/'.$slug);

        if (is_file($dir.'/landing.blade.php')) {
            $this->components->warn("resources/landings/{$slug}/ already exists; surface kept.");

            return self::SUCCESS;
        }

        $landingStub = resource_path('stubs/landing.blade.php');
        $loginStub = resource_path('stubs/login-shell.blade.php');

        foreach ([$landingStub, $loginStub] as $stub) {
            if (! is_file($stub)) {
                $this->error('Missing surface stub: '.$stub);

                return self::FAILURE;
            }
        }

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error("Could not create {$dir}.");

            return self::FAILURE;
        }

        $landing = (string) file_get_contents($landingStub);
        $login = (string) file_get_contents($loginStub);

        if (file_put_contents($dir.'/landing.blade.php', $landing) === false
            || file_put_contents($dir.'/login-shell.blade.php', $login) === false) {
            $this->error("Could not write the surface files in {$dir}.");

            return self::FAILURE;
        }

        $problems = app(LandingLint::class)->check($landing);
        $published = $problems === [];

        file_put_contents($dir.'/meta.json', json_encode([
            'enabled' => $published,
            'published_at' => $published ? now()->toIso8601String() : null,
            'published_by' => $published ? 'tenant:make' : null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");

        if (! $published) {
            $this->error("Surface lint failed; resources/landings/{$slug}/ was created unpublished:");
            foreach ($problems as $problem) {
                $this->line('  - '.$problem);
            }

            return self::FAILURE;
        }

        $this->components->info("Wrote resources/landings/{$slug}/landing.blade.php");
        $this->components->info("Wrote resources/landings/{$slug}/login-shell.blade.php (published)");

        return self::SUCCESS;
    }

    private function phpString(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    private function createDomain(Tenant $tenant, string $host): void
    {
        $host = Domain::normalize($host);

        if ($existing = Domain::where('domain', $host)->first()) {
            $this->warn("Domain {$host} already belongs to tenant {$existing->tenant_id}; skipped.");

            return;
        }

        Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => $host,
            'is_primary' => true,
            // Local/dev domains are trusted on creation. For production domains
            // clear this and verify out-of-band.
            'verified_at' => now(),
        ]);

        Cache::forget(Domain::cacheKey($host));

        $this->components->info("Domain {$host} -> tenant {$tenant->id}");
    }

    private function createAdmin(Tenant $tenant, string $email): void
    {
        if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
            $this->warn("User {$email} already exists; skipped.");

            return;
        }

        $password = Str::password(16);

        User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            // Left NULL on purpose: a tenant admin may enter through any of
            // their tenant's domains (see User::canLoginThrough).
            'domain_id' => null,
            'name' => $tenant->name,
            'email' => $email,
            'password' => bcrypt($password),
            'role' => User::ROLE_TENANT_ADMIN,
        ]);

        $this->components->info("tenant_admin {$email} / {$password}");
        $this->components->warn('Store that password now — it is not recoverable.');
    }

    private function nextSteps(string $slug): void
    {
        $this->newLine();
        $this->line('<fg=yellow>Next steps</>');
        $this->line('  1. Point the domain at your server. On Windows, edit');
        $this->line('     C:\Windows\System32\drivers\etc\hosts (as Administrator) and add:');
        $this->line('       127.0.0.1  <your-domain>');
        $this->line('     then run: ipconfig /flushdns');
        $this->line("  2. Add the host to vite.config.js server.allowedHosts if you use 'npm run dev'.");
        $this->line("  3. Put this tenant's images in public/tenants/{$slug}/images/.");
        $this->line("  4. Edit the surface in resources/landings/{$slug}/landing.blade.php");
        $this->line('     and the copy in config/tenants/'.$slug.'.php;');
        $this->line("     run: php artisan landing:lint {$slug}");
        $this->line("     and: php artisan tenant:validate {$slug}");
    }
}
