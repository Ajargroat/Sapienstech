<?php

namespace Tests\Feature\Student;

use App\Models\CompanyExamResult;
use App\Models\Domain;
use App\Models\ExamCompany;
use App\Models\Student;
use App\Models\Tenant;
use App\Support\ReportCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The student report card (کارنامه) contract and page.
 *
 * These tests pin the things that make the page trustworthy rather than the
 * exact markup:
 *  - the score is "percent correct", never the negative-marked percent;
 *  - a subject is weak on its NEWEST value, not its historical average;
 *  - absences are counted but never scored, so an absence cannot flatter a
 *    student's average;
 *  - the pass line and the class-average switch come from tenant config and
 *    change what the student sees;
 *  - nothing leaks across tenants.
 */
class StudentReportCardTest extends TestCase
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

    private function company(Tenant $tenant): ExamCompany
    {
        // One company per tenant, reused by every row an assertion adds.
        return ExamCompany::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'ghalamchi'],
            ['name' => 'قلم‌چی', 'color' => '#E11D48'],
        );
    }

    /** @param array<string, mixed> $overrides */
    private function companyResult(Tenant $tenant, Student $student, array $overrides = []): CompanyExamResult
    {
        return CompanyExamResult::create(array_merge([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'company_id' => $this->company($tenant)->id,
            'title' => 'آزمون آزمایشی',
            'exam_date' => '2026-06-20',
            'status' => 'completed',
            'total_questions' => 100,
            'correct_count' => 70,
            'wrong_count' => 20,
            'blank_count' => 10,
            'percent' => 70,
            'exam_rank' => 1840,
            'participants' => 92000,
            'lesson_percents' => ['زیست‌شناسی' => 80, 'ریاضی' => 40],
        ], $overrides));
    }

    public function test_report_card_page_renders_the_contract_and_rows(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        $this->companyResult($tenant, $student);

        $html = $this->actingAs($student, 'student')
            ->get("http://{$host}/student/report-card")
            ->assertOk()
            ->assertSee('کارنامهٔ من', false)
            ->assertSee('روند پیشرفت', false)
            ->assertSee('وضعیت درس‌ها', false)
            ->assertSee('همهٔ کارنامه‌ها', false)
            ->assertSee('آزمون آزمایشی', false)
            ->assertDontSee('به‌زودی', false)
            ->getContent();

        // The row is a real row, not an empty-state placeholder.
        $this->assertSame(1, substr_count($html, 'rc-log-row'));
    }

    public function test_page_renders_an_honest_empty_state_without_data(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id]);

        $html = $this->actingAs($student, 'student')
            ->get("http://{$host}/student/report-card")
            ->assertOk()
            ->assertSee('هنوز کارنامه‌ای ثبت نشده است', false)
            ->getContent();

        // No fake numbers, no empty chart frame.
        $this->assertStringNotContainsString('rc-chart', $html);
        $this->assertStringNotContainsString('rc-log-row', $html);
    }

    public function test_weak_subject_is_judged_on_its_newest_value_not_its_average(): void
    {
        [$tenant] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id]);

        // ریاضی was failing, then recovered above the pass line. Judging by
        // average would keep it in the weak list; the newest value must not.
        $this->companyResult($tenant, $student, [
            'title' => 'قدیمی',
            'exam_date' => '2026-05-01',
            'percent' => 30,
            'lesson_percents' => ['ریاضی' => 20, 'شیمی' => 30],
        ]);
        $this->companyResult($tenant, $student, [
            'title' => 'جدید',
            'exam_date' => '2026-06-01',
            'percent' => 75,
            'lesson_percents' => ['ریاضی' => 72, 'شیمی' => 45],
        ]);

        $report = ReportCard::forStudent($student->fresh());

        $weakNames = array_column($report['weak'], 'name');

        $this->assertNotContains('ریاضی', $weakNames, 'a recovered subject must leave the weak list');
        $this->assertContains('شیمی', $weakNames, 'the subject still under the line must stay');
    }

    public function test_absence_is_listed_and_never_scored(): void
    {
        [$tenant] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id]);

        $this->companyResult($tenant, $student, ['title' => 'حاضر', 'percent' => 60]);
        $this->companyResult($tenant, $student, [
            'title' => 'غایب',
            'exam_date' => '2026-07-01',
            'status' => 'absent',
            'percent' => null,
            'correct_count' => null,
            'wrong_count' => null,
            'blank_count' => null,
            'lesson_percents' => null,
        ]);

        $report = ReportCard::forStudent($student->fresh());

        $this->assertSame(1, $report['summary']['absences']);
        $this->assertSame(1, $report['summary']['attended']);
        // One scored exam only: the absence must not drag the average down.
        $this->assertSame(1, $report['summary']['scored']);
        $this->assertSame(60.0, $report['summary']['average']);
        $this->assertSame('غایب', $report['absences'][0]['title']);
    }

    public function test_class_average_is_hidden_by_default_and_shown_when_the_tenant_enables_it(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        $this->companyResult($tenant, $student);

        $this->assertFalse(ReportCard::showsClassAverage(), 'competition must be opt-in');

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/report-card")
            ->assertOk()
            ->assertDontSee('مقایسه با میانگین شرکت‌کنندگان', false);

        site_override(['report' => ['report_show_class_average' => true]]);

        $this->assertTrue(ReportCard::showsClassAverage());

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/report-card")
            ->assertOk()
            ->assertSee('مقایسه با میانگین شرکت‌کنندگان', false);
    }

    public function test_pass_line_defaults_to_fifty_and_follows_the_tenant_setting(): void
    {
        [$tenant] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        $this->companyResult($tenant, $student, ['percent' => 60, 'lesson_percents' => ['ریاضی' => 60]]);

        $this->assertSame(50.0, ReportCard::passLine(), 'the documented default is 50%');

        $report = ReportCard::forStudent($student->fresh());
        $this->assertSame([], $report['weak'], '60% is above the default line');

        // Raise the line above the student's score: the subject must move
        // into the weak list, which is the whole point of the setting.
        site_override(['report' => ['pass_line' => 75]]);

        $this->assertSame(75.0, ReportCard::passLine());

        $report = ReportCard::forStudent($student->fresh());
        $this->assertSame(['ریاضی'], array_column($report['weak'], 'name'));
    }

    public function test_pass_line_is_clamped_so_a_bad_value_cannot_break_the_page(): void
    {
        [$tenant] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        site_override(['report' => ['pass_line' => 400]]);
        $this->assertSame(100.0, ReportCard::passLine());

        site_override(['report' => ['pass_line' => -20]]);
        $this->assertSame(0.0, ReportCard::passLine());

        site_override(['report' => ['pass_line' => 'not-a-number']]);
        $this->assertSame(50.0, ReportCard::passLine(), 'a malformed value falls back to the default');
    }

    public function test_report_card_never_shows_another_tenants_student(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain();
        [$tenantB] = $this->tenantWithDomain();

        $studentB = Student::factory()->create(['tenant_id' => $tenantB->id]);
        $this->companyResult($tenantB, $studentB, ['title' => 'کارنامهٔ تننت دیگر']);

        app()->instance('tenant', $tenantA);
        $studentA = Student::factory()->create(['tenant_id' => $tenantA->id]);

        $this->actingAs($studentA, 'student')
            ->get("http://{$hostA}/student/report-card")
            ->assertOk()
            ->assertDontSee('کارنامهٔ تننت دیگر', false);
    }
}
