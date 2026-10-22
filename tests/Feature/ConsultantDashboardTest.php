<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\ScheduleItem;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsultantDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();
        $host = Str::lower(Str::random(10)).'.sapienstech.test';
        Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => $host,
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

    public function test_dashboard_lists_only_the_current_tenants_students(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain();
        [$tenantB, $hostB] = $this->tenantWithDomain();

        $userA = $this->consultantFor($tenantA);

        app()->instance('tenant', $tenantA);
        $studentA = Student::factory()->create(['name' => 'Tenant A Student']);

        app()->instance('tenant', $tenantB);
        Student::factory()->create(['name' => 'Tenant B Student']);

        $response = $this->actingAs($userA)
            ->get("http://{$hostA}/consultant/dashboard");

        $response->assertOk();
        $response->assertSee('Tenant A Student');
        $response->assertDontSee('Tenant B Student');

        $this->assertTrue($studentA->exists);
    }

    public function test_dashboard_search_is_tenant_scoped(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain();
        [$tenantB, $hostB] = $this->tenantWithDomain();

        $userA = $this->consultantFor($tenantA);

        app()->instance('tenant', $tenantA);
        $studentA = Student::factory()->create(['name' => 'Shared Name', 'email' => 'a@tenanta.test']);

        app()->instance('tenant', $tenantB);
        $studentB = Student::factory()->create(['name' => 'Shared Name', 'email' => 'b@tenantb.test']);

        $response = $this->actingAs($userA)
            ->get("http://{$hostA}/consultant/dashboard?search=Shared");

        $response->assertOk();

        $response->assertSee(route('consultant.student.profile', $studentA->id), false);
        $response->assertDontSee(route('consultant.student.profile', $studentB->id), false);
    }

    public function test_consultant_cannot_open_another_tenants_student_page(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain();
        [$tenantB, $hostB] = $this->tenantWithDomain();

        $userA = $this->consultantFor($tenantA);

        app()->instance('tenant', $tenantB);
        $studentB = Student::factory()->create();

        $response = $this->actingAs($userA)
            ->get("http://{$hostA}/consultant/students/{$studentB->id}");

        $response->assertNotFound();
    }

    public function test_dashboard_overview_cards_and_insights_render(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $student = Student::factory()->create([
            'name' => 'Alpha Overview Student',
            'grade' => 'دهم',
        ]);

        $today = Carbon::today();
        $weekStart = $today->copy()->subDays(($today->dayOfWeek + 1) % 7)->startOfDay();

        ScheduleItem::factory()->create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'title' => 'جلسهٔ نمای کلی',
            'week_start_date' => $weekStart->toDateString(),
            'start_datetime' => $weekStart->copy()->addDay()->setTime(9, 0),
            'end_datetime' => $weekStart->copy()->addDay()->setTime(10, 0),
            'is_completed' => true,
        ]);

        $html = $this->actingAs($user)
            ->get("http://{$host}/consultant/dashboard")
            ->assertOk()
            ->assertSee('public.consultant.stats', false)
            ->assertSee('public.consultant.roster', false)
            ->assertSee('public.consultant.insights', false)
            ->assertSee('ترکیب دانش‌آموزان')
            ->assertSee('Alpha Overview Student')
            ->assertSee('درخواست‌های عضویت')
            ->assertSee('پرداخت‌های در انتظار')
            ->getContent();

        $this->assertStringContainsString('public.consultant.students.grid', $html);
        $this->assertStringContainsString('dash-stat--primary', $html);
        $this->assertStringContainsString('dash-people', $html);
    }

    public function test_top_navigation_has_dashboard_blog_and_direct_chat(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();

        $user = $this->consultantFor($tenant);

        $response = $this->actingAs($user)
            ->get("http://{$host}/consultant/dashboard");

        $response->assertOk();
        $response->assertSee(route('consultant.dashboard'), false);
        // Blog management lives in the top navigation itself, as its own
        // first-class section (it used to be a settings-hub tab).
        $response->assertSee(route('consultant.blog.index'), false);
        $response->assertSee(route('consultant.direct-chat'), false);
    }
}
