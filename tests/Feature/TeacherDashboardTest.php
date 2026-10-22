<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();
        $host = Str::lower(Str::random(10)).'.sapienstech.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    public function test_dashboard_renders_the_shared_overview_and_rail(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $teacher = User::factory()->teacher()->create(['tenant_id' => $tenant->id]);

        $html = $this->actingAs($teacher)
            ->get("http://{$host}/teacher/dashboard")
            ->assertOk()
            ->assertSee('public.teacher.welcome', false)
            ->assertSee('public.teacher.stats', false)
            ->assertSee('public.teacher.rail', false)
            ->assertSee('public.teacher.quick_actions', false)
            ->assertSee('پیشرفت بررسی تحویل‌ها')
            ->getContent();

        $this->assertStringContainsString('dash-stats', $html);
        $this->assertStringContainsString('dash-layout', $html);
        $this->assertStringContainsString('dash-panel', $html);
        $this->assertStringContainsString('public.teacher.quick_actions', $html);
        $this->assertStringContainsString('برنامهٔ امروز', $html);
        $this->assertStringContainsString('مهلت‌های نزدیک', $html);
    }
}
