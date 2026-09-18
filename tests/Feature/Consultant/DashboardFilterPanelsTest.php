<?php

namespace Tests\Feature\Consultant;

use App\Models\CompanyExamResult;
use App\Models\Domain;
use App\Models\ExamCompany;
use App\Models\ScheduleItem;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use App\Models\Test;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The dashboard filter carousel's domain pages (exams / report cards /
 * schedule): each narrows students by what exists in their related
 * workspaces, and the exam/schedule panels post bulk assignments that
 * re-derive exactly the filtered set server-side (the former
 * «اقدامات گروهی» picker absorbed into the dashboard).
 *
 * Like the other consultant suites these run against the migrated dev DB
 * via DatabaseTransactions.
 */
class DashboardFilterPanelsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenantWithConsultant(string $host): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        \Illuminate\Support\Facades\Cache::forget(Domain::cacheKey($host));
        $user = User::factory()->for($tenant)->consultant()->create();

        return [$tenant, $user];
    }

    private function student(Tenant $tenant, string $name, array $attrs = []): Student
    {
        return Student::factory()->for($tenant)->create(array_merge(['name' => $name], $attrs));
    }

    private function assignExam(
        Student $student,
        User $consultant,
        string $lesson,
        string $status = 'scheduled',
    ): StudentAssignedQuiz {
        $test = Test::create([
            'tenant_id' => $student->tenant_id,
            'test_title' => 'آزمون '.$lesson,
            'lesson' => $lesson,
            'exam_type' => 'quiz',
            'total_marks' => 20,
            'created_by_user_id' => $consultant->id,
        ]);

        return StudentAssignedQuiz::create([
            'tenant_id' => $student->tenant_id,
            'test_id' => $test->id,
            'student_id' => $student->id,
            'assigned_by_user_id' => $consultant->id,
            'assigned_at' => now(),
            'scheduled_at' => '2026-06-20 10:00:00',
            'status' => $status,
            'is_completed' => $status === 'completed',
        ]);
    }

    private function scheduleBlock(Student $student, string $start, array $attrs = []): ScheduleItem
    {
        return ScheduleItem::create(array_merge([
            'tenant_id' => $student->tenant_id,
            'student_id' => $student->id,
            'week_start_date' => substr($start, 0, 10),
            'title' => 'بلوک آزمایشی',
            'start_datetime' => $start,
            'end_datetime' => date('Y-m-d H:i:s', strtotime($start.' +1 hour')),
            'item_type' => 'consultant_event',
            'created_by_type' => 'user',
            'color' => '#3b82f6',
            'is_completed' => 0,
        ], $attrs));
    }

    public function test_panels_render_with_assignment_sections(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('dpanel-a.test');
        $this->student($tenant, 'DpanelAlice');

        $this->actingAs($consultant)
            ->get('http://dpanel-a.test/consultant/dashboard')
            ->assertOk()
            ->assertSee('وضعیت آزمون')
            ->assertSee('منبع کارنامه')
            ->assertSee('روز هفته')
            ->assertSee('واگذاری گروهی آزمون')
            ->assertSee('ساخت گروهی بلوک هفتگی');
    }

    public function test_exam_panel_narrows_students_by_status_and_lesson(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('dpanel-b.test');
        $scheduled = $this->student($tenant, 'DpanelScheduled');
        $done = $this->student($tenant, 'DpanelDone');

        $this->assignExam($scheduled, $consultant, 'ریاضی', 'scheduled');
        $this->assignExam($done, $consultant, 'شیمی', 'completed');

        $this->actingAs($consultant)
            ->get('http://dpanel-b.test/consultant/dashboard?exam_status=scheduled')
            ->assertOk()
            ->assertSee('DpanelScheduled')
            ->assertDontSee('DpanelDone');

        $this->actingAs($consultant)
            ->get('http://dpanel-b.test/consultant/dashboard?exam_lesson=%D8%B4%DB%8C%D9%85%DB%8C')
            ->assertOk()
            ->assertSee('DpanelDone')
            ->assertDontSee('DpanelScheduled');
    }

    public function test_schedule_panel_narrows_students_by_weekday_and_completion(): void
    {
        // 2026-09-05 is a Saturday (شنبه, day_index 0).
        [$tenant, $consultant] = $this->makeTenantWithConsultant('dpanel-c.test');
        $saturday = $this->student($tenant, 'DpanelSaturday');
        $busy = $this->student($tenant, 'DpanelBusy');

        $this->scheduleBlock($saturday, '2026-09-05 08:00:00');
        $this->scheduleBlock($busy, '2026-09-06 10:00:00', ['is_completed' => 1]);

        $this->actingAs($consultant)
            ->get('http://dpanel-c.test/consultant/dashboard?schedule_day=0')
            ->assertOk()
            ->assertSee('DpanelSaturday')
            ->assertDontSee('DpanelBusy');

        $this->actingAs($consultant)
            ->get('http://dpanel-c.test/consultant/dashboard?schedule_done=done')
            ->assertOk()
            ->assertSee('DpanelBusy')
            ->assertDontSee('DpanelSaturday');
    }

    public function test_report_panel_narrows_students_by_source_and_status(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('dpanel-d.test');
        $pending = $this->student($tenant, 'DpanelPending');
        $other = $this->student($tenant, 'DpanelOther');

        $company = ExamCompany::factory()->for($tenant)->create([
            'name' => 'ماز پنل',
            'slug' => 'dpanel-maz-'.Str::lower(Str::random(6)),
        ]);

        CompanyExamResult::factory()->for($pending)->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'title' => 'آزمون آزمایشی پنل',
            'exam_date' => '2026-06-20',
            'status' => 'pending',
        ]);

        $this->actingAs($consultant)
            ->get("http://dpanel-d.test/consultant/dashboard?report_source={$company->slug}")
            ->assertOk()
            ->assertSee('DpanelPending')
            ->assertDontSee('DpanelOther');

        $this->actingAs($consultant)
            ->get('http://dpanel-d.test/consultant/dashboard?report_status=pending')
            ->assertOk()
            ->assertSee('DpanelPending')
            ->assertDontSee('DpanelOther');

        // 'internal' targets students with finished consultant-made exams.
        $this->assignExam($other, $consultant, 'فیزیک', 'completed');

        $this->actingAs($consultant)
            ->get('http://dpanel-d.test/consultant/dashboard?report_source=internal')
            ->assertOk()
            ->assertSee('DpanelOther')
            ->assertDontSee('DpanelPending');
    }

    public function test_multi_value_filters_narrow_students(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('dpanel-f.test');
        $tenth = $this->student($tenant, 'DpanelMultiTenth', ['grade' => 'دهم']);
        $eleventh = $this->student($tenant, 'DpanelMultiEleventh', ['grade' => 'یازدهم']);
        $this->student($tenant, 'DpanelMultiTwelfth', ['grade' => 'دوازدهم']);

        // "grade[]" multi value: both grades match, the third does not.
        $this->actingAs($consultant)
            ->get('http://dpanel-f.test/consultant/dashboard?'.http_build_query(['grade' => ['دهم', 'یازدهم']]))
            ->assertOk()
            ->assertSee('DpanelMultiTenth')
            ->assertSee('DpanelMultiEleventh')
            ->assertDontSee('DpanelMultiTwelfth');

        // Two exam statuses at once; a student without exams matches neither.
        $this->assignExam($tenth, $consultant, 'ریاضی', 'scheduled');
        $this->assignExam($eleventh, $consultant, 'شیمی', 'completed');

        $this->actingAs($consultant)
            ->get('http://dpanel-f.test/consultant/dashboard?'.http_build_query(['exam_status' => ['scheduled', 'completed']]))
            ->assertOk()
            ->assertSee('DpanelMultiTenth')
            ->assertSee('DpanelMultiEleventh')
            ->assertDontSee('DpanelMultiTwelfth');
    }

    public function test_dashboard_assignment_reapplies_multi_value_filters(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('dpanel-g.test');
        $scheduled = $this->student($tenant, 'DpanelMassTarget');
        $done = $this->student($tenant, 'DpanelMassDone');
        $this->student($tenant, 'DpanelMassUntouched');

        $this->assignExam($scheduled, $consultant, 'زیست‌شناسی', 'scheduled');
        $this->assignExam($done, $consultant, 'شیمی', 'completed');

        $newTest = Test::create([
            'tenant_id' => $tenant->id,
            'test_title' => 'آزمون چندوضعیتی',
            'lesson' => 'ریاضی',
            'exam_type' => 'comprehensive',
            'created_by_user_id' => $consultant->id,
        ]);

        $this->actingAs($consultant)->post('http://dpanel-g.test/consultant/bulk/exams', [
            'test_id' => $newTest->id,
            'select_all' => 1,
            'exam_status' => ['scheduled', 'completed'],
            'filter_open' => 'exams',
        ])->assertRedirect();

        $assigned = StudentAssignedQuiz::withoutGlobalScopes()
            ->where('test_id', $newTest->id)
            ->pluck('student_id')
            ->sort()
            ->values();

        $this->assertSame([$scheduled->id, $done->id], $assigned->all());
    }

    public function test_dashboard_assignment_reapplies_domain_filters(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('dpanel-e.test');
        $scheduled = $this->student($tenant, 'DpanelTarget');
        $untouched = $this->student($tenant, 'DpanelUntouched');

        $this->assignExam($scheduled, $consultant, 'زیست‌شناسی', 'scheduled');

        $newTest = Test::create([
            'tenant_id' => $tenant->id,
            'test_title' => 'آزمون پنلی جدید',
            'lesson' => 'ریاضی',
            'exam_type' => 'comprehensive',
            'created_by_user_id' => $consultant->id,
        ]);

        // Mirrors the exam panel's assignment POST: select_all plus the whole
        // filter stack; only the student matching exam_status=scheduled wins.
        $this->actingAs($consultant)->post('http://dpanel-e.test/consultant/bulk/exams', [
            'test_id' => $newTest->id,
            'select_all' => 1,
            'exam_status' => 'scheduled',
            'exam_lesson' => 'زیست‌شناسی',
            'filter_open' => 'exams',
        ])->assertRedirect();

        $this->assertSame(1, StudentAssignedQuiz::withoutGlobalScopes()->where('test_id', $newTest->id)->count());
        $this->assertSame(
            $scheduled->id,
            StudentAssignedQuiz::withoutGlobalScopes()->where('test_id', $newTest->id)->value('student_id')
        );
    }
}
