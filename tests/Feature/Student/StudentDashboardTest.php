<?php

namespace Tests\Feature\Student;

use App\Models\Domain;
use App\Models\ScheduleItem;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
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

    private function weekStart(): Carbon
    {
        $today = Carbon::today();

        return $today->copy()->subDays(($today->dayOfWeek + 1) % 7)->startOfDay();
    }

    public function test_dashboard_renders_the_seven_day_board_and_new_regions(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'دانش‌آموز نمونه',
            'grade' => 'دهم',
        ]);

        $weekStart = $this->weekStart();
        $item = ScheduleItem::factory()->create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'created_by_type' => 'student',
            'created_by_user_id' => null,
            'created_by_student_id' => $student->id,
            'title' => 'جلسهٔ آزمایشی هفته',
            'week_start_date' => $weekStart->toDateString(),
            'start_datetime' => $weekStart->copy()->addDay()->setTime(10, 0),
            'end_datetime' => $weekStart->copy()->addDay()->setTime(11, 0),
            'is_completed' => true,
            'color' => 'var(--c-primary)',
        ]);

        $html = $this->actingAs($student, 'student')
            ->get("http://{$host}/student/dashboard")
            ->assertOk()
            ->assertSee('public.student.stats', false)
            ->assertSee('public.student.schedule', false)
            ->assertSee('public.student.exams', false)
            ->assertSee('public.student.results', false)
            ->assertSee('public.student.next_exam', false)
            ->assertSee('public.student.quick_actions', false)
            ->getContent();

        $this->assertStringContainsString('dash-week', $html);
        $this->assertSame(7, substr_count($html, 'class="dash-week-day'), 'the week board must always render seven day columns');
        $this->assertStringContainsString('data-sched-id="'.$item->id.'"', $html);
        $this->assertStringContainsString('جلسهٔ آزمایشی هفته', $html);
        $this->assertStringContainsString('public.student.welcome', $html);
        $this->assertStringContainsString('پیشرفت این هفته', $html);
    }

    public function test_dashboard_without_schedule_still_renders_the_rest(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'دانش‌آموز بدون برنامه',
        ]);

        $html = $this->actingAs($student, 'student')
            ->get("http://{$host}/student/dashboard")
            ->assertOk()
            ->assertSee('برنامه‌ای برای این هفته ثبت نشده')
            ->assertSee('public.student.next_exam', false)
            ->getContent();

        $this->assertStringNotContainsString('class="dash-week"', $html);
        $this->assertStringContainsString('فعلاً آزمونی زمان‌بندی نشده', $html);
    }
}
