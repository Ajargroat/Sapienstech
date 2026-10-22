<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Grant (or revoke) the operations console for ONE account.
 *
 * The console is ours, so who may open it is a deliberate act, never a side
 * effect of a role change. `--revoke` takes it back the same way.
 *
 *   php artisan ops:grant someone@example.com
 *   php artisan ops:grant someone@example.com --revoke
 */
class OpsGrant extends Command
{
    protected $signature = 'ops:grant
        {email : The account email (unique per tenant, so include --tenant when it repeats)}
        {--tenant= : Tenant slug, when the same email exists on several tenants}
        {--revoke : Take the console away instead of granting it}';

    protected $description = 'Allow (or forbid) one account to use the /ops operations console';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $slug = $this->option('tenant');

        $query = User::withoutGlobalScopes()->where('email', $email);

        if (is_string($slug) && $slug !== '') {
            $tenantId = Tenant::query()->where('slug', $slug)->value('id');

            if ($tenantId === null) {
                $this->error("No tenant with slug `{$slug}`.");

                return self::FAILURE;
            }

            $query->where('tenant_id', $tenantId);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->error("No user with email `{$email}`.");

            return self::FAILURE;
        }

        if ($users->count() > 1) {
            $this->error('That email exists on more than one tenant; pass --tenant=<slug>.');
            $this->line($users->map(fn (User $u) => "  #{$u->id} tenant={$u->tenant_id}")->implode(PHP_EOL));

            return self::FAILURE;
        }

        $user = $users->first();
        $grant = ! $this->option('revoke');

        $user->forceFill(['is_platform_admin' => $grant])->save();

        $this->info(sprintf(
            '%s ops access for #%d <%s> (tenant %s).',
            $grant ? 'Granted' : 'Revoked',
            $user->id,
            $user->email,
            $user->tenant_id ?? 'none',
        ));

        return self::SUCCESS;
    }
}
