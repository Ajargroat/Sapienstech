<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tenant_cannot_see_another_tenants_students(): void
    {
        // Create two separate tenants.
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        // Pretend the request currently belongs to Tenant A.
        app()->instance('tenant', $tenantA);

        // StudentFactory + BelongsToTenant should automatically
        // assign tenant_id to Tenant A.
        $studentA = Student::factory()->create();

        // Switch to Tenant B.
        app()->instance('tenant', $tenantB);

        // This student should automatically belong to Tenant B.
        $studentB = Student::factory()->create();

        // Switch back to Tenant A.
        app()->instance('tenant', $tenantA);

        // Tenant A should see its own student.
        $this->assertTrue(
            Student::whereKey($studentA->id)->exists()
        );

        // Tenant A must NOT see Tenant B's student.
        $this->assertFalse(
            Student::whereKey($studentB->id)->exists()
        );
    }
}
