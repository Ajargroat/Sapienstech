<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Same pattern as ConsultantDashboardTest: a tenant with a domain
     * that IdentifyTenant can resolve from the request host.
     */
    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();
        $host = Str::lower(Str::random(10)).'.sapienstech.test';

        Domain::create([
            'tenant_id'  => $tenant->id,
            'domain'     => $host,
            'is_primary' => true,
        ]);

        Cache::forget("domain:{$host}");

        return [$tenant, $host];
    }

    /**
     * Bind the tenant before creating the user so BelongsToTenant is
     * happy; overrides let tests set email/password per scenario.
     */
    private function userFor(Tenant $tenant, array $overrides = []): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
        ], $overrides));
    }

    public function test_guest_sees_the_landing_page_not_the_dashboard(): void
    {
        [, $host] = $this->tenantWithDomain();

        $response = $this->get("http://{$host}/");

        $response->assertOk();
        $response->assertSee(route('login'), false);
    }

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        [, $host] = $this->tenantWithDomain();

        $this->get("http://{$host}/consultant/dashboard")
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_consultant_can_login_and_reaches_the_dashboard(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->userFor($tenant);

        $response = $this->post("http://{$host}/login", [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('consultant.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_returns_guest_to_the_page_they_were_trying_to_reach(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->userFor($tenant);

        $this->get("http://{$host}/consultant/dashboard")
            ->assertRedirect(route('login'));

        $this->post("http://{$host}/login", [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('consultant.dashboard'));
    }

    public function test_wrong_password_keeps_the_user_logged_out(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->userFor($tenant);

        $this->post("http://{$host}/login", [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * THE critical isolation test (roadmap Phase 6, acceptance criteria):
     * correct email + correct password, but on the WRONG domain.
     */
    public function test_user_of_tenant_a_cannot_login_on_tenant_b_domain(): void
    {
        [$tenantA] = $this->tenantWithDomain();
        [, $hostB] = $this->tenantWithDomain();

        $userA = $this->userFor($tenantA);

        $this->post("http://{$hostB}/login", [
            'email'    => $userA->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    /**
     * users.email is unique PER TENANT, so the same email may legally
     * exist twice. The domain must decide which user is authenticated.
     */
    public function test_same_email_in_two_tenants_authenticates_the_correct_one(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain();
        [$tenantB, $hostB] = $this->tenantWithDomain();

        $email = 'shared.'.Str::lower(Str::random(8)).'@example.test';

        $this->userFor($tenantA, ['email' => $email, 'password' => 'secret-A']);
        $userB = $this->userFor($tenantB, ['email' => $email, 'password' => 'secret-B']);

        $this->post("http://{$hostB}/login", [
            'email'    => $email,
            'password' => 'secret-B',
        ])->assertRedirect(route('consultant.dashboard'));

        $this->assertAuthenticatedAs($userB);
    }

    public function test_platform_admin_cannot_login_on_a_tenant_domain(): void
    {
        [, $host] = $this->tenantWithDomain();

        // Platform admins live outside every tenant (tenant_id is NULL).
        app()->forgetInstance('tenant');
        $admin = User::factory()->create([
            'tenant_id' => null,
            'role'      => 'platform_admin',
        ]);

        $this->post("http://{$host}/login", [
            'email'    => $admin->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_authenticated_user_visiting_login_is_sent_to_the_dashboard(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->userFor($tenant);

        $this->actingAs($user)
            ->get("http://{$host}/login")
            ->assertRedirect(route('consultant.dashboard'));
    }

    public function test_user_can_logout_and_the_session_is_destroyed(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->userFor($tenant);

        $this->actingAs($user);

        $this->post("http://{$host}/logout")
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
