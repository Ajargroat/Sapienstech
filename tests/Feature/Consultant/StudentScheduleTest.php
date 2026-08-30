<?php

namespace Tests\Feature\Consultant;

use App\Models\Domain;
use App\Models\ScheduleItem;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * NOTE ON RUNNING THESE TESTS:
 * The uploaded project tree has no database/migrations directory -- the
 * schema appears to be provisioned from the SQL dump rather than Laravel
 * migrations. These tests therefore use DatabaseTransactions (schema
 * assumed already present in the configured test DB) rather than
 * RefreshDatabase. I do not have a working copy of this app (no vendor/,
 * no artisan, no PHP interpreter in this sandbox) so I was NOT able to
 * actually execute this file -- see the chat response for exactly what
 * was and wasn't verified.
 */
class StudentScheduleTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenantWithConsultant(string $host): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        $user = User::factory()->for($tenant)->consultant()->create();

        return [$tenant, $user];
    }

    private function weekStart(): string
    {
        return Carbon::now()->startOfWeek(Carbon::SATURDAY)->toDateString();
    }

    public function test_authorized_consultant_can_load_schedule_page(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('tenant-a.test');
        $student = Student::factory()->for($tenant)->create();

        $response = $this->actingAs($consultant)
            ->get("http://tenant-a.test/consultant/students/{$student->id}/schedule");

        $response->assertOk();
    }

    public function test_schedule_items_endpoint_returns_week_events(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('tenant-a.test');
        $student = Student::factory()->for($tenant)->create();
        ScheduleItem::factory()->for($tenant)->for($student)->create(['title' => 'مطالعه فیزیک']);

        $response = $this->actingAs($consultant)
            ->getJson("http://tenant-a.test/consultant/students/{$student->id}/schedule/items?week_start_date={$this->weekStart()}");

        $response->assertOk()
            ->assertJsonPath('week_start_date', $this->weekStart())
            ->assertJsonFragment(['title' => 'مطالعه فیزیک']);
    }

    public function test_consultant_can_create_a_schedule_item(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('tenant-a.test');
        $student = Student::factory()->for($tenant)->create();

        $response = $this->actingAs($consultant)->postJson(
            "http://tenant-a.test/consultant/students/{$student->id}/schedule/items",
            [
                'title' => 'حل تست ریاضی',
                'week_start_date' => $this->weekStart(),
                'day_index' => 1,
                'start_time' => '09:00',
                'end_time' => '10:30',
                'color' => '#22c55e',
                'book_name' => 'گسسته',
                'page_count' => 10,
                'test_count' => 20,
            ]
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('schedule_items', [
            'student_id' => $student->id,
            'tenant_id' => $tenant->id,
            'title' => 'حل تست ریاضی',
            'book_name' => 'گسسته',
        ]);
    }

    public function test_create_rejects_end_before_start(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('tenant-a.test');
        $student = Student::factory()->for($tenant)->create();

        $response = $this->actingAs($consultant)->postJson(
            "http://tenant-a.test/consultant/students/{$student->id}/schedule/items",
            [
                'title' => 'نامعتبر',
                'week_start_date' => $this->weekStart(),
                'day_index' => 1,
                'start_time' => '10:00',
                'end_time' => '09:00',
            ]
        );

        $response->assertStatus(422);
    }

    public function test_consultant_can_update_and_delete_an_item(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('tenant-a.test');
        $student = Student::factory()->for($tenant)->create();
        $item = ScheduleItem::factory()->for($tenant)->for($student)->create();

        $update = $this->actingAs($consultant)->putJson(
            "http://tenant-a.test/consultant/students/{$student->id}/schedule/items/{$item->id}",
            [
                'title' => 'عنوان به‌روزشده',
                'week_start_date' => $this->weekStart(),
                'day_index' => 2,
                'start_time' => '11:00',
                'end_time' => '12:00',
            ]
        );
        $update->assertOk()->assertJsonPath('event.title', 'عنوان به‌روزشده');

        $delete = $this->actingAs($consultant)->deleteJson(
            "http://tenant-a.test/consultant/students/{$student->id}/schedule/items/{$item->id}"
        );
        $delete->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('schedule_items', ['id' => $item->id]);
    }

    public function test_consultant_cannot_access_a_student_in_another_tenant(): void
    {
        [, $consultantA] = $this->makeTenantWithConsultant('tenant-a.test');
        [$tenantB] = $this->makeTenantWithConsultant('tenant-b.test');
        $studentB = Student::factory()->for($tenantB)->create();

        $response = $this->actingAs($consultantA)
            ->get("http://tenant-a.test/consultant/students/{$studentB->id}/schedule");

        $response->assertNotFound();
    }

    public function test_consultant_cannot_update_another_tenants_schedule_item_by_guessing_id(): void
    {
        [$tenantA, $consultantA] = $this->makeTenantWithConsultant('tenant-a.test');
        [$tenantB] = $this->makeTenantWithConsultant('tenant-b.test');

        $studentA = Student::factory()->for($tenantA)->create();
        $studentB = Student::factory()->for($tenantB)->create();
        $itemB = ScheduleItem::factory()->for($tenantB)->for($studentB)->create();

        // Consultant A pairs their own (real) student with tenant B's item id.
        $response = $this->actingAs($consultantA)->putJson(
            "http://tenant-a.test/consultant/students/{$studentA->id}/schedule/items/{$itemB->id}",
            [
                'title' => 'تلاش برای دستکاری',
                'week_start_date' => $this->weekStart(),
                'day_index' => 0,
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('schedule_items', ['id' => $itemB->id, 'title' => $itemB->title]);
    }

    public function test_consultant_cannot_delete_another_tenants_schedule_item(): void
    {
        [$tenantA, $consultantA] = $this->makeTenantWithConsultant('tenant-a.test');
        [$tenantB] = $this->makeTenantWithConsultant('tenant-b.test');

        $studentA = Student::factory()->for($tenantA)->create();
        $studentB = Student::factory()->for($tenantB)->create();
        $itemB = ScheduleItem::factory()->for($tenantB)->for($studentB)->create();

        $response = $this->actingAs($consultantA)->deleteJson(
            "http://tenant-a.test/consultant/students/{$studentA->id}/schedule/items/{$itemB->id}"
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('schedule_items', ['id' => $itemB->id]);
    }

    public function test_consultant_cannot_reach_a_different_same_tenant_students_item(): void
    {
        [$tenant, $consultant] = $this->makeTenantWithConsultant('tenant-a.test');
        $studentOne = Student::factory()->for($tenant)->create();
        $studentTwo = Student::factory()->for($tenant)->create();
        $itemForTwo = ScheduleItem::factory()->for($tenant)->for($studentTwo)->create();

        // Same tenant, but the item belongs to a different student than the
        // one named in the URL.
        $response = $this->actingAs($consultant)->deleteJson(
            "http://tenant-a.test/consultant/students/{$studentOne->id}/schedule/items/{$itemForTwo->id}"
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('schedule_items', ['id' => $itemForTwo->id]);
    }
}
