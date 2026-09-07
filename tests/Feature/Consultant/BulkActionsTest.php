<?php

namespace Tests\Feature\Consultant;

use App\Models\BulkAction;
use App\Models\Domain;
use App\Models\ScheduleItem;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use App\Models\StudentTestAttempt;
use App\Models\Test;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The «اقدامات گروهی» workspace: bulk exam + schedule assignment (by name and
 * by filter), the revertable history, and the tenant-scoping guarantees.
 */
class BulkActionsTest extends TestCase
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
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    private function consultantFor(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->consultant()->create(['tenant_id' => $tenant->id]);
    }

    private function studentsFor(Tenant $tenant, int $n, array $attrs = [])
    {
        app()->instance('tenant', $tenant);
        $made = [];
        for ($i = 0; $i < $n; $i++) {
            $made[] = Student::factory()->create(array_merge([
                'tenant_id' => $tenant->id,
            ], $attrs));
        }
        return $made;
    }

    private function test_for(Tenant $tenant, ?User $user = null): Test
    {
        app()->instance('tenant', $tenant);

        return Test::create([
            'tenant_id' => $tenant->id,
            'test_title' => 'آزمون گروهی',
            'lesson' => 'ریاضی',
            'exam_type' => 'quiz',
            'created_by_user_id' => $user?->id ?? User::factory()->consultant()->create(['tenant_id' => $tenant->id])->id,
        ]);
    }

    public function test_bulk_exam_page_loads(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/bulk/exams")
            ->assertOk()
            ->assertSee('اقدامات گروهی');
    }

    public function test_bulk_exam_assigns_by_explicit_ids(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $test = $this->test_for($tenant);
        $students = $this->studentsFor($tenant, 3);

        $this->actingAs($user)->post("http://{$host}/consultant/bulk/exams", [
            'test_id' => $test->id,
            'student_ids' => array_map(fn ($s) => $s->id, $students),
        ])->assertRedirect();

        $this->assertSame(3, StudentAssignedQuiz::withoutGlobalScopes()->where('test_id', $test->id)->count());
        $this->assertSame(3, (int) BulkAction::withoutGlobalScopes()->where('kind', 'exam')->sum('affected_count'));
    }

    public function test_bulk_exam_skips_already_assigned_students(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $test = $this->test_for($tenant);
        $students = $this->studentsFor($tenant, 3);

        // Pre-assign the first student.
        app()->instance('tenant', $tenant);
        StudentAssignedQuiz::create([
            'test_id' => $test->id,
            'student_id' => $students[0]->id,
            'assigned_by_user_id' => $user->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)->post("http://{$host}/consultant/bulk/exams", [
            'test_id' => $test->id,
            'student_ids' => array_map(fn ($s) => $s->id, $students),
        ])->assertRedirect();

        // Still exactly 3 total (1 pre-existing + 2 new), no duplicate.
        $this->assertSame(3, StudentAssignedQuiz::withoutGlobalScopes()->where('test_id', $test->id)->count());
    }

    public function test_select_all_reapplies_filters_server_side(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $test = $this->test_for($tenant);

        $this->studentsFor($tenant, 2, ['grade' => 'دهم']);
        $this->studentsFor($tenant, 1, ['grade' => 'یازدهم']);

        // select_all with grade=دهم must hit exactly the 2 matching students,
        // regardless of any student_ids the client might have sent.
        $this->actingAs($user)->post("http://{$host}/consultant/bulk/exams", [
            'test_id' => $test->id,
            'select_all' => 1,
            'grade' => 'دهم',
            'student_ids' => [],
        ])->assertRedirect();

        $this->assertSame(2, StudentAssignedQuiz::withoutGlobalScopes()->where('test_id', $test->id)->count());
    }

    public function test_foreign_student_ids_are_dropped(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        [$otherTenant] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $test = $this->test_for($tenant);

        $mine = $this->studentsFor($tenant, 1);
        $foreign = $this->studentsFor($otherTenant, 1);

        $this->actingAs($user)->post("http://{$host}/consultant/bulk/exams", [
            'test_id' => $test->id,
            'student_ids' => [$mine[0]->id, $foreign[0]->id],
        ])->assertRedirect();

        // Only the tenant's own student got the assignment.
        $this->assertSame(1, StudentAssignedQuiz::withoutGlobalScopes()->where('test_id', $test->id)->count());
        $this->assertSame(0, StudentAssignedQuiz::withoutGlobalScopes()->where('student_id', $foreign[0]->id)->count());
    }

    public function test_bulk_exam_rejects_foreign_test(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        [$otherTenant] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $foreignTest = $this->test_for($otherTenant);
        $students = $this->studentsFor($tenant, 1);

        $this->actingAs($user)->post("http://{$host}/consultant/bulk/exams", [
            'test_id' => $foreignTest->id,
            'student_ids' => [$students[0]->id],
        ])->assertSessionHasErrors('test_id');
    }

    public function test_bulk_schedule_assigns_blocks(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $students = $this->studentsFor($tenant, 4);

        $this->actingAs($user)->post("http://{$host}/consultant/bulk/schedule", [
            'title' => 'کلاس شنبه',
            'week_start_date' => '2026-09-05',
            'day_index' => 0,
            'start_time' => '08:00',
            'end_time' => '09:30',
            'student_ids' => array_map(fn ($s) => $s->id, $students),
        ])->assertRedirect();

        $this->assertSame(4, ScheduleItem::withoutGlobalScopes()->where('title', 'کلاس شنبه')->count());
        $this->assertSame(4, (int) BulkAction::withoutGlobalScopes()->where('kind', 'schedule')->sum('affected_count'));
    }

    public function test_history_lists_and_reverts_a_batch(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $test = $this->test_for($tenant);
        $students = $this->studentsFor($tenant, 2);

        $this->actingAs($user)->post("http://{$host}/consultant/bulk/exams", [
            'test_id' => $test->id,
            'student_ids' => array_map(fn ($s) => $s->id, $students),
        ]);

        $action = BulkAction::withoutGlobalScopes()->where('kind', 'exam')->latest()->firstOrFail();

        $this->actingAs($user)
            ->get("http://{$host}/consultant/bulk/history")
            ->assertOk()
            ->assertSee('آزمون گروهی');

        $this->actingAs($user)->delete("http://{$host}/consultant/bulk/history/{$action->id}")
            ->assertRedirect();

        $this->assertSame(0, StudentAssignedQuiz::withoutGlobalScopes()->where('bulk_action_id', $action->id)->count());
        $this->assertTrue($action->fresh()->isReverted());
    }

    public function test_revert_is_blocked_when_an_attempt_exists(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $test = $this->test_for($tenant);
        $students = $this->studentsFor($tenant, 2);

        $this->actingAs($user)->post("http://{$host}/consultant/bulk/exams", [
            'test_id' => $test->id,
            'student_ids' => array_map(fn ($s) => $s->id, $students),
        ]);

        $action = BulkAction::withoutGlobalScopes()->where('kind', 'exam')->latest()->firstOrFail();
        $assignment = StudentAssignedQuiz::withoutGlobalScopes()->where('bulk_action_id', $action->id)->first();

        StudentTestAttempt::create([
            'tenant_id' => $tenant->id,
            'student_id' => $assignment->student_id,
            'test_id' => $test->id,
            'assignment_id' => $assignment->id,
            'status' => 'completed',
            'time_taken_seconds' => 60,
            'completed_at' => now(),
        ]);

        $this->actingAs($user)->delete("http://{$host}/consultant/bulk/history/{$action->id}")
            ->assertStatus(422);

        // The assignments survive.
        $this->assertSame(2, StudentAssignedQuiz::withoutGlobalScopes()->where('bulk_action_id', $action->id)->count());
    }

    public function test_bulk_feature_flag_blocks_routes(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        site_override(['features' => ['bulk_actions' => false]]);

        $this->actingAs($user)->get("http://{$host}/consultant/bulk/exams")->assertNotFound();
    }
}
