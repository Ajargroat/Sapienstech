<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsultantDashboardTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Creates a tenant with a resolvable domain, mirroring how
     * IdentifyTenant resolves the tenant from the request host.
     */
    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();

        $host = Str::lower(Str::random(10)).'.sapienstech.test';

        Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => $host,
            'is_primary' => true,
        ]);

        // IdentifyTenant caches the domain lookup; make sure each test
        // starts from a clean cache entry for its (unique, random) host.
        Cache::forget("domain:{$host}");

        return [$tenant, $host];
    }

    public function test_dashboard_lists_only_the_current_tenants_students(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain();
        [$tenantB, $hostB] = $this->tenantWithDomain();

        app()->instance('tenant', $tenantA);
        $studentA = Student::factory()->create(['name' => 'Tenant A Student']);

        app()->instance('tenant', $tenantB);
        Student::factory()->create(['name' => 'Tenant B Student']);

        $response = $this->get("http://{$hostA}/consultant/dashboard");

        $response->assertOk();
        $response->assertSee('Tenant A Student');
        $response->assertDontSee('Tenant B Student');
        $this->assertTrue($studentA->exists);
    }

    public function test_dashboard_search_is_tenant_scoped(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain();
        [$tenantB, $hostB] = $this->tenantWithDomain();

        app()->instance('tenant', $tenantA);
        Student::factory()->create(['name' => 'Shared Name', 'email' => 'a@tenanta.test']);

        app()->instance('tenant', $tenantB);
        Student::factory()->create(['name' => 'Shared Name', 'email' => 'b@tenantb.test']);

        $response = $this->get("http://{$hostA}/consultant/dashboard?search=Shared");

        $response->assertOk();
        $response->assertSee('a@tenanta.test');
        $response->assertDontSee('b@tenantb.test');
    }

    public function test_consultant_cannot_open_another_tenants_student_page(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain();
        [$tenantB, $hostB] = $this->tenantWithDomain();

        app()->instance('tenant', $tenantB);
        $studentB = Student::factory()->create();

        // Tenant A tries to open Tenant B's student by guessing/changing the id.
        $response = $this->get("http://{$hostA}/consultant/students/{$studentB->id}");

        $response->assertNotFound();
    }

    public function test_top_navigation_has_dashboard_blog_and_direct_chat(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $response = $this->get("http://{$host}/consultant/dashboard");

        $response->assertOk();
        $response->assertSee(route('consultant.dashboard'), false);
        $response->assertSee(route('consultant.blog'), false);
        $response->assertSee(route('consultant.direct-chat'), false);
    }
}
