<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainAccessTest extends TestCase
{
    use DatabaseTransactions;

    /** One tenant, two domains — returns [$tenant, $hostA, $hostB]. */
    private function tenantWithTwoDomains(): array
    {
        $tenant = Tenant::factory()->create();

        $hosts = [];
        foreach ([true, false] as $primary) {
            $host = Str::lower(Str::random(10)).'.sapienstech.test';
            Domain::create([
                'tenant_id'  => $tenant->id,
                'domain'     => $host,
                'is_primary' => $primary,
            ]);
            Cache::forget("domain:{$host}");
            $hosts[] = $host;
        }

        return [$tenant, $hosts[0], $hosts[1]];
    }

    private function userFor(Tenant $tenant, array $overrides = []): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
        ], $overrides));
    }

    public function test_tenant_admin_can_login_through_any_of_the_tenants_domains(): void
    {
        [$tenant, , $hostB] = $this->tenantWithTwoDomains();
        $admin = $this->userFor($tenant, ['role' => 'tenant_admin']);

        // Logging in through the NON-primary domain must work for admins.
        $this->post("http://{$hostB}/login", [
            'email'    => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('consultant.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_pinned_consultant_can_login_through_their_own_domain(): void
    {
        [$tenant, $hostOwn] = $this->tenantWithTwoDomains();

        app()->instance('tenant', $tenant);
        $ownDomain = Domain::where('domain', $hostOwn)->first();

        $consultant = $this->userFor($tenant, ['domain_id' => $ownDomain->id]);

        $this->post("http://{$hostOwn}/login", [
            'email'    => $consultant->email,
            'password' => 'password',
        ])->assertRedirect(route('consultant.dashboard'));

        $this->assertAuthenticatedAs($consultant);
    }

    /** The adversarial one: right email + right password, wrong domain. */
    public function test_pinned_consultant_cannot_login_through_another_tenant_domain(): void
    {
        [$tenant, $hostOwn, $hostOther] = $this->tenantWithTwoDomains();

        app()->instance('tenant', $tenant);
        $ownDomain = Domain::where('domain', $hostOwn)->first();

        $consultant = $this->userFor($tenant, ['domain_id' => $ownDomain->id]);

        $this->post("http://{$hostOther}/login", [
            'email'    => $consultant->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unpinned_consultant_keeps_access_to_every_tenant_domain(): void
    {
        [$tenant, , $hostB] = $this->tenantWithTwoDomains();

        // domain_id NULL = legacy account, not pinned to a domain.
        $consultant = $this->userFor($tenant);

        $this->post("http://{$hostB}/login", [
            'email'    => $consultant->email,
            'password' => 'password',
        ])->assertRedirect(route('consultant.dashboard'));

        $this->assertAuthenticatedAs($consultant);
    }

    public function test_pinned_consultant_session_is_rejected_on_a_foreign_domain(): void
    {
        [$tenant, $hostOwn, $hostOther] = $this->tenantWithTwoDomains();

        app()->instance('tenant', $tenant);
        $ownDomain = Domain::where('domain', $hostOwn)->first();

        $consultant = $this->userFor($tenant, ['domain_id' => $ownDomain->id]);

        $this->post("http://{$hostOwn}/login", [
            'email'    => $consultant->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($consultant);

        // Same tenant, different domain: the request-level guard must end
        // the session and send them back to the login page.
        $this->get("http://{$hostOther}/consultant/dashboard")
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_admin_only_gates_deny_consultant_staff(): void
    {
        [$tenant] = $this->tenantWithTwoDomains();

        $admin = $this->userFor($tenant, ['role' => 'tenant_admin']);
        $staff = $this->userFor($tenant, ['role' => 'consultant_staff']);

        $this->assertTrue(Gate::forUser($admin)->allows('manage-website'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-users'));

        $this->assertFalse(Gate::forUser($staff)->allows('manage-website'));
        $this->assertFalse(Gate::forUser($staff)->allows('manage-users'));
    }
}
