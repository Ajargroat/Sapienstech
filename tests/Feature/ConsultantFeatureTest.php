<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsultantFeatureTest extends TestCase
{
    use DatabaseTransactions;

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

    private function consultantFor(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->consultant()->create([
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_dashboard_loads(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $response = $this->actingAs($user)->get("http://{$host}/consultant/dashboard");

        $response->assertOk();
        $response->assertSee('داشبورد');
    }

    public function test_enabled_blog_loads(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $response = $this->actingAs($user)->get("http://{$host}/consultant/blog");

        $response->assertOk();
        $response->assertSee('وبلاگ');
    }

    public function test_disabled_feature_is_blocked(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        config(['consultant.features.blog_management' => false]);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/blog")
            ->assertNotFound();
    }
}
