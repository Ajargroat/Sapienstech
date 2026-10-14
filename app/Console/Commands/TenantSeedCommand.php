<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\DrkafimentoringTenantSeeder;
use Illuminate\Console\Command;

/**
 * Populates one tenant with its demo/real content. The production entry point
 * for `database/seeders/*TenantSeeder`: db:seed needs the class name typed and
 * cannot tell the operator that the tenant row is missing.
 */
class TenantSeedCommand extends Command
{
    protected $signature = 'tenant:seed {slug : Tenant slug to populate}';

    protected $description = 'Populate one tenant with its content seeder';

    /** Slug => seeder. Explicit, so an unknown slug fails loudly. */
    private const SEEDERS = [
        DrkafimentoringTenantSeeder::SLUG => DrkafimentoringTenantSeeder::class,
    ];

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');
        $class = self::SEEDERS[$slug] ?? null;

        if ($class === null) {
            $this->components->error("No tenant seeder is registered for '{$slug}'.");
            $this->line('Known slugs: '.implode(', ', array_keys(self::SEEDERS)));

            return self::FAILURE;
        }

        if (! Tenant::query()->where('slug', $slug)->exists()) {
            $this->components->error("No tenant with slug '{$slug}'. Run tenant:make first.");

            return self::FAILURE;
        }

        // --force: db:seed asks for confirmation in production, which would
        // hang a non-interactive deploy. This command IS the confirmation.
        return $this->call('db:seed', ['--class' => $class, '--force' => true]);
    }
}
