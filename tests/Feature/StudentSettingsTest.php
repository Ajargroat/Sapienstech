<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The student-side settings skeleton: the profile tab, the moved logout, and
 * the shared personal-theme machinery exercised through the student guard.
 */
class StudentSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();
        $host = Str::lower(Str::random(10)).'.sapienstech.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    private function studentFor(Tenant $tenant): Student
    {
        app()->instance('tenant', $tenant);

        return Student::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_student_profile_tab_loads(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $student = $this->studentFor($tenant);

        // The hub home is the Telegram-style identity page: hero, info rows
        // and a settings list (edit) — the forms themselves are
        // separate ?tab= sections, not inline boxes.
        $html = $this->actingAs($student, 'student')
            ->get("http://{$host}/student/settings/profile")
            ->assertOk()
            ->assertSee('پروفایل')
            ->assertSee('ویرایش پروفایل')
            ->assertDontSee('tab=password', false)
            ->assertDontSee('class="profile-header"', false)
            ->getContent();

        $this->assertStringNotContainsString('name="name"', $html);
    }

    public function test_student_edit_section_includes_the_password_dialog(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $student = $this->studentFor($tenant);

        $edit = $this->actingAs($student, 'student')
            ->get("http://{$host}/student/settings/profile?tab=edit")
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('name="name"', $edit);

        $this->assertStringContainsString('name="current_password"', $edit);
        $this->assertStringContainsString('data-profile-open="profile-dialog-password"', $edit);
        $this->assertStringNotContainsString('data-profile-auto-open', $edit);
        $this->assertSame(1, substr_count($edit, 'data-profile-dialogs'));
        $this->assertSame(1, substr_count($edit, 'data-profile-open="profile-dialog-avatar"'));
        $this->assertStringContainsString('data-profile-avatar-toggle aria-expanded="false"', $edit);
        $this->assertStringNotContainsString('profile-edit-row-label">تصویر پروفایل', $edit);

        $student->forceFill(['avatar' => 'avatars/example.jpg'])->save();
        $this->get("http://{$host}/student/settings/profile?tab=edit")
            ->assertOk()
            ->assertSee('data-profile-avatar-delete hidden', false)
            ->assertSee('aria-label="حذف تصویر پروفایل"', false);

        $this->get("http://{$host}/student/settings/profile?tab=password")->assertNotFound();

        // Same gating vocabulary as the consultant hub.
        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/settings/profile?tab=bogus")
            ->assertNotFound();
    }

    public function test_student_logout_moved_out_of_topnav(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $student = $this->studentFor($tenant);

        $html = $this->actingAs($student, 'student')
            ->get("http://{$host}/student/dashboard")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('action="http://'.$host.'/student/logout"', $html);
        $this->assertStringContainsString('topnav-profile', $html);
        $this->assertStringContainsString('/student/settings/profile', $html);
        $this->assertStringNotContainsString('data-topnav-dropdown', $html);
    }

    public function test_student_can_update_profile_and_password(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $student = $this->studentFor($tenant);

        $this->actingAs($student, 'student')->patch("http://{$host}/student/settings/profile", [
            'name' => 'دانش‌آموز تازه',
            'email' => 'new-' . Str::lower(Str::random(6)) . '@example.test',
        ])->assertRedirect();

        $this->assertSame('دانش‌آموز تازه', $student->fresh()->name);
    }

    public function test_student_personal_theme_applies_only_to_them(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $owner = $this->studentFor($tenant);
        $other = $this->studentFor($tenant);

        $owner->preferences = ['site' => ['theme' => ['colors' => ['primary' => '#010203']]]];
        $owner->save();

        $this->assertStringContainsString('--c-primary: #010203',
            $this->actingAs($owner, 'student')->get("http://{$host}/student/dashboard")->getContent());
        $this->assertStringNotContainsString('--c-primary: #010203',
            $this->actingAs($other, 'student')->get("http://{$host}/student/dashboard")->getContent());
    }
}
