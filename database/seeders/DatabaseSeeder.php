<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * The local `php artisan db:seed` entry point.
 *
 * Only seeds a tenant whose row already exists — `db:seed` runs against
 * whatever database it is pointed at, and a missing tenant is a setup step
 * (tenant:make), not a seed failure.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Tenant::query()->where('slug', DrkafimentoringTenantSeeder::SLUG)->exists()) {
            $this->command?->warn('Tenant '.DrkafimentoringTenantSeeder::SLUG.' does not exist; run tenant:make first.');

            return;
        }

        $this->call(DrkafimentoringTenantSeeder::class);
    }
}
