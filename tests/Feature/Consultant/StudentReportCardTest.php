<?php

namespace Tests\Feature\Consultant;

use App\Models\CompanyExamResult;
use App\Models\Domain;
use App\Models\ExamCompany;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use App\Models\StudentTestAttempt;
use App\Models\Test;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The report-card workspace merges company_exam_results with finished
 * consultant-made exams into one card grid; these tests cover the merge, the
 * source filter, tenant isolation and the removal of the old
 * source-permissions destination.
 *
 * Like StudentScheduleTest these run against the already-migrated dev DB via
 * DatabaseTransactions.
 */
class StudentReportCardTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenantWithConsultant(string $host): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        $user = User::factory()->for($tenant)->consultant()->create();

        return [$tenant, $user];
    }

    private function finishedInternalExam(Student $student, User $consultant): StudentAssignedQuiz
    {
        $test = new Test();
        $test->tenant_id = $student->tenant_id;
        $test->test_title = 'آزمون داخلی ریاضی — فصل مشتق';
        $test->lesson = 'ریاضی';
        $test->exam_type = 'quiz';
        $test->total_marks = 20;
        $test->created_by_user_id = $consultant->id;
        $test->save();

        $assignment = StudentAssignedQuiz::create([
            'tenant_id' => $student->tenant_id,
            'test_id' => $test->id,
            'student_id' => $student->id,
            'assigned_by_user_id' => $consultant->id,
            'assigned_at' => now(),
            'scheduled_at' => '2026-06-20 10:00:00',
            'status' => 'completed',
            'is_completed' => true,
        ]);

        StudentTestAttempt::create([
            'tenant_id' => $student->tenant_id,
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'started_at' => '2026-06-20 10:00:00',
            'completed_at' => '2026-06-20 10:40:00',
            'score_raw' => 17.5,
            'score_simple_percent' => 87.5,
            'score_negative_percent' => 87.5,
            'time_taken_seconds' => 2400,
        ]);

        return $assignment;
    }

    public function test_report_card_page_loads(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('rc-a.test');
        $student = Student::factory()->for($tenant)->create();

        $response = $this->actingAs($consultant)
            ->get("http://rc-a.test/consultant/students/{$student->id}/report-card");

        $response->assertOk();
        $response->assertSee('کارنامه‌ها');
    }

    public function test_grid_merges_company_and_internal_cards(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('rc-a.test');
        $student = Student::factory()->for($tenant)->create();

        $company = ExamCompany::factory()->for($tenant)->create([
            'name' => 'قلم‌چی',
            'slug' => 'ghalamchi',
            'color' => '#E11D48',
        ]);

        CompanyExamResult::factory()->for($student)->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'title' => 'آزمون آزمایشی ۲۵ — جامع تجربی',
            'exam_date' => '2026-06-20',
        ]);

        $this->finishedInternalExam($student, $consultant);

        $response = $this->actingAs($consultant)
            ->get("http://rc-a.test/consultant/students/{$student->id}/report-card");

        $response->assertOk()
            ->assertSee('آزمون آزمایشی ۲۵ — جامع تجربی')
            ->assertSee('آزمون داخلی ریاضی — فصل مشتق')
            ->assertSee('قلم‌چی')
            ->assertSee('درون‌ساز');
    }

    public function test_source_filter_narrows_the_grid(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('rc-a.test');
        $student = Student::factory()->for($tenant)->create();

        $company = ExamCompany::factory()->for($tenant)->create(['slug' => 'maz', 'name' => 'ماز']);
        CompanyExamResult::factory()->for($student)->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'title' => 'آزمون جامع ماز — نوبت اول',
            'exam_date' => '2026-05-29',
        ]);
        $this->finishedInternalExam($student, $consultant);

        $companyOnly = $this->actingAs($consultant)
            ->get("http://rc-a.test/consultant/students/{$student->id}/report-card?source=maz");

        $companyOnly->assertOk()
            ->assertSee('آزمون جامع ماز — نوبت اول')
            ->assertDontSee('آزمون داخلی ریاضی — فصل مشتق');

        $internalOnly = $this->actingAs($consultant)
            ->get("http://rc-a.test/consultant/students/{$student->id}/report-card?source=internal");

        $internalOnly->assertOk()
            ->assertSee('آزمون داخلی ریاضی — فصل مشتق')
            ->assertDontSee('آزمون جامع ماز — نوبت اول');
    }

    public function test_search_matches_company_and_internal_titles(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('rc-a.test');
        $student = Student::factory()->for($tenant)->create();

        $company = ExamCompany::factory()->for($tenant)->create(['slug' => 'maz', 'name' => 'ماز']);
        CompanyExamResult::factory()->for($student)->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'title' => 'آزمون جامع ماز — نوبت اول',
            'exam_date' => '2026-05-29',
        ]);
        $this->finishedInternalExam($student, $consultant);

        $response = $this->actingAs($consultant)
            ->get("http://rc-a.test/consultant/students/{$student->id}/report-card?search=مشتق");

        $response->assertOk()
            ->assertSee('آزمون داخلی ریاضی — فصل مشتق')
            ->assertDontSee('آزمون جامع ماز — نوبت اول');
    }

    public function test_report_card_is_tenant_isolated(): void
    {
        [$tenantA, $consultantA] = $this->makeTenantWithConsultant('rc-a.test');
        [$tenantB] = $this->makeTenantWithConsultant('rc-b.test');
        $studentB = Student::factory()->for($tenantB)->create();

        $response = $this->actingAs($consultantA)
            ->get("http://rc-a.test/consultant/students/{$studentB->id}/report-card");

        $response->assertNotFound();
    }

    public function test_source_permissions_destination_is_gone(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('rc-a.test');
        $student = Student::factory()->for($tenant)->create();

        $response = $this->actingAs($consultant)
            ->get("http://rc-a.test/consultant/students/{$student->id}/source-permissions");

        $response->assertNotFound();
    }
}
