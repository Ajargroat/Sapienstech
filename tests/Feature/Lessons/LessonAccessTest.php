<?php

namespace Tests\Feature\Lessons;

use App\Models\BookLesson;
use App\Models\Domain;
use App\Models\LessonBlock;
use App\Models\LessonImport;
use App\Models\LessonReadingProgress;
use App\Models\LessonSourcePage;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Who may reach the lesson surfaces.
 *
 * This file exists because the surface is mounted under TWO panels from ONE
 * file, and that shape has two failure modes that are invisible in a browser:
 *
 *   1. a `require` outside a group registers the same routes with no prefix
 *      and no `auth` — measured during this plan, the staff editor was reachable
 *      at `/lessons/editor/{lesson}` with only `web` in its stack;
 *   2. a panel gate that names only one side, so a teacher can author through
 *      `/consultant/*` (or vice versa) inside a shell they do not belong to.
 *
 * Both are pinned here, plus tenant isolation for the page-image route.
 */
class LessonAccessTest extends TestCase
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

        return User::factory()->consultant()->create(['tenant_id' => $tenant->id]);
    }

    private function teacherFor(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->teacher()->create(['tenant_id' => $tenant->id]);
    }

    private function lessonFor(Tenant $tenant, array $attributes = []): BookLesson
    {
        app()->instance('tenant', $tenant);

        $import = LessonImport::create([
            'book_title' => 'زیست‌شناسی دهم',
            'pdf_path' => 'lessons/1/pdf/book.pdf',
            'status' => 'ready',
        ]);

        return BookLesson::create($attributes + [
            'lesson_import_id' => $import->id,
            'book_title' => 'زیست‌شناسی دهم',
            'lesson_title' => 'فتوسنتز',
            'status' => 'draft',
        ]);
    }

    /* ------------------------------------------------------------ the leak */

    public function test_the_staff_surface_is_not_reachable_outside_a_panel(): void
    {
        $this->assertNull(
            Route::getRoutes()->getByName('lessons.index'),
            'The staff surface must never be registered at the top level: that'
            .' mount inherits no prefix and no auth (it is a bare `require`).'
        );

        $this->assertNull(Route::getRoutes()->getByName('lessons.editor.edit'));
    }

    public function test_every_lesson_route_sits_under_a_panel_and_requires_auth(): void
    {
        $staff = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains((string) $route->getName(), 'lessons.'))
            // `student.` is the material library + reader, `ops.` is OUR pipeline
            // console. Neither is a tenant panel; the ops one carries its own
            // platform gate and is asserted separately below.
            ->filter(fn ($route) => ! str_starts_with((string) $route->getName(), 'student.'))
            ->filter(fn ($route) => ! str_starts_with((string) $route->getName(), 'ops.'));

        $this->assertNotEmpty($staff, 'the staff surface should be registered at all');

        foreach ($staff as $route) {
            $name = (string) $route->getName();
            $middleware = $route->gatherMiddleware();

            $this->assertMatchesRegularExpression(
                '/^(consultant|teacher)\.lessons\./',
                $name,
                "{$name} is not under a panel name root"
            );

            $this->assertContains('auth', $middleware, "{$name} is registered without auth");
        }
    }

    /**
     * The operations console is the one lesson-named surface that is NOT a
     * tenant panel. It must therefore carry the platform gate on every route —
     * an ops route without `ops.access` would expose our pipeline to any
     * authenticated user on any tenant host.
     */
    public function test_every_ops_route_carries_the_platform_gate(): void
    {
        $ops = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'ops.'));

        $this->assertNotEmpty($ops, 'the operations console should be registered');

        foreach ($ops as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('ops.access', $middleware, $route->getName().' is missing the platform gate');
            $this->assertContains('auth', $middleware, $route->getName().' is registered without auth');
        }
    }

    /* --------------------------------------------------------------- staff */

    public function test_a_consultant_reaches_the_editor(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $lesson = $this->lessonFor($tenant);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/lessons/editor/{$lesson->id}")
            ->assertOk()
            ->assertSee($lesson->lesson_title);
    }

    public function test_a_teacher_reaches_the_same_editor_through_their_own_panel(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->teacherFor($tenant);
        $lesson = $this->lessonFor($tenant);

        // The second mount exists for exactly this: a school teacher works in
        // the teacher shell, with the same controllers and the same access rules.
        $this->actingAs($user)
            ->get("http://{$host}/teacher/lessons/editor/{$lesson->id}")
            ->assertOk();
    }

    public function test_a_teacher_cannot_author_through_the_consultant_panel(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->teacherFor($tenant);
        $lesson = $this->lessonFor($tenant);

        // Standing in the wrong panel is a 404, not a "you are not allowed":
        // the surface never confirms that a lesson exists to a stranger.
        $this->actingAs($user)
            ->get("http://{$host}/consultant/lessons/editor/{$lesson->id}")
            ->assertNotFound();
    }

    public function test_an_authenticated_student_without_a_staff_role_gets_nothing(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => 'دهم']);
        $lesson = $this->lessonFor($tenant);

        // The list is the important one: it binds no {lesson}, so nothing
        // downstream guards it. It must refuse on its own.
        $this->actingAs($student, 'student')
            ->get("http://{$host}/lessons")
            ->assertNotFound();

        $this->actingAs($student, 'student')
            ->get("http://{$host}/consultant/lessons")
            ->assertNotFound();
    }

    public function test_a_guest_is_sent_to_login_not_to_the_editor(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $lesson = $this->lessonFor($tenant);

        $this->get("http://{$host}/consultant/lessons/editor/{$lesson->id}")
            ->assertRedirect();

        $this->get("http://{$host}/lessons/editor/{$lesson->id}")
            ->assertNotFound();
    }

    public function test_the_flag_off_hides_the_surface_entirely(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $lesson = $this->lessonFor($tenant);

        site_override(['features' => ['lesson_library' => false]]);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/lessons")
            ->assertNotFound();

        $this->actingAs($user)
            ->get("http://{$host}/consultant/lessons/editor/{$lesson->id}")
            ->assertNotFound();
    }

    /* ------------------------------------------------------------ tenancy */

    public function test_a_lesson_of_another_tenant_is_unreachable(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain();
        $userA = $this->consultantFor($tenantA);

        [$tenantB] = $this->tenantWithDomain();
        $lessonB = $this->lessonFor($tenantB);

        // Guarded by tenant_id in LessonAccess, not only by the global scope.
        $this->actingAs($userA)
            ->get("http://{$hostA}/consultant/lessons/editor/{$lessonB->id}")
            ->assertNotFound();
    }

    /* ---------------------------------------------------------- the reader */

    public function test_a_student_reads_a_published_lesson(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => 'دهم']);
        $lesson = $this->lessonFor($tenant, ['status' => 'published', 'grade' => 'دهم']);

        LessonBlock::create([
            'lesson_id' => $lesson->id,
            'position' => 0,
            'type' => 'heading',
            'data' => ['text' => 'فتوسنتز چیست؟'],
            'anchor' => 'photosynthesis',
        ]);
        LessonBlock::create([
            'lesson_id' => $lesson->id,
            'position' => 1,
            'type' => 'paragraph',
            'data' => ['text' => 'گیاهان نور را به انرژی تبدیل می‌کنند.'],
        ]);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lesson-reader/{$lesson->id}")
            ->assertOk()
            ->assertSee('فتوسنتز چیست؟')
            ->assertSee('گیاهان نور را به انرژی تبدیل می‌کنند.')
            ->assertSee('student-lesson-reader');
    }

    public function test_a_student_cannot_read_a_draft_or_another_grade(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => 'دهم']);
        $draft = $this->lessonFor($tenant, ['status' => 'draft', 'grade' => 'دهم']);
        $otherGrade = $this->lessonFor($tenant, ['status' => 'published', 'grade' => 'یازدهم']);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lesson-reader/{$draft->id}")
            ->assertNotFound();

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lesson-reader/{$otherGrade->id}")
            ->assertNotFound();
    }

    public function test_the_source_page_image_is_scoped_to_the_reader(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => 'دهم']);
        $lesson = $this->lessonFor($tenant, ['status' => 'published', 'grade' => 'دهم']);

        LessonSourcePage::create([
            'lesson_import_id' => $lesson->lesson_import_id,
            'page_number' => 12,
            'image_path' => 'lessons/1/pages/12.png',
            'char_count' => 900,
        ]);

        // The row exists but the PNG was never written: 404 from the file
        // check, which is still "you cannot read a page you may not read".
        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lesson-reader/{$lesson->id}/pages/12")
            ->assertNotFound();

        // A draft's pages are not readable, even for the same student.
        $draft = $this->lessonFor($tenant, ['status' => 'draft', 'grade' => 'دهم']);
        LessonSourcePage::create([
            'lesson_import_id' => $draft->lesson_import_id,
            'page_number' => 3,
            'image_path' => 'lessons/2/pages/3.png',
            'char_count' => 500,
        ]);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lesson-reader/{$draft->id}/pages/3")
            ->assertNotFound();
    }

    public function test_reading_progress_only_moves_forward(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => 'دهم']);
        $lesson = $this->lessonFor($tenant, ['status' => 'published', 'grade' => 'دهم']);

        $url = "http://{$host}/student/lesson-reader/{$lesson->id}/progress";

        $this->actingAs($student, 'student')->postJson($url, ['index' => 3])
            ->assertOk()->assertJson(['index' => 3]);

        // A refresh that lands on an earlier section must not undo reading.
        $this->actingAs($student, 'student')->postJson($url, ['index' => 1])
            ->assertOk()->assertJson(['index' => 3]);

        $this->assertDatabaseHas('lesson_reading_progress', [
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'max_section_index' => 3,
        ]);
    }

    /**
     * The feature is useless if nothing links to it. Both shells must carry the
     * entry, in the panel the actor actually owns — this is the defect that
     * shipped: the routes existed and worked, but no nav pointed at them.
     */
    public function test_the_consultant_shell_links_to_the_lesson_library(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $consultant = $this->consultantFor($tenant);

        $this->actingAs($consultant)
            ->get("http://{$host}/consultant/dashboard")
            ->assertOk()
            ->assertSee('کتاب و درس')
            ->assertSee('/consultant/lessons', false);
    }

    public function test_the_teacher_shell_links_to_the_lesson_library(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $teacher = $this->teacherFor($tenant);

        $this->actingAs($teacher)
            ->get("http://{$host}/teacher/dashboard")
            ->assertOk()
            ->assertSee('کتاب و درس')
            ->assertSee('/teacher/lessons', false);
    }

    public function test_a_student_is_not_shown_a_staff_lesson_link(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => 'دهم']);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/dashboard")
            ->assertOk()
            ->assertDontSee('/consultant/lessons', false)
            ->assertDontSee('/teacher/lessons', false);
    }

    /**
     * The student library is the ONE «درس‌ها» destination: the file list and
     * the book lessons share it, so a student never has to guess which tab
     * holds which. A published lesson must therefore be linked from there.
     */
    public function test_the_student_library_links_to_readable_lessons(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => 'دهم']);
        $lesson = $this->lessonFor($tenant, [
            'status' => 'published',
            'grade' => 'دهم',
            'lesson_title' => 'ساختار یاخته',
        ]);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lessons")
            ->assertOk()
            ->assertSee('درس‌های کتاب')
            ->assertSee('ساختار یاخته')
            ->assertSee("/student/lesson-reader/{$lesson->id}", false);
    }

    public function test_a_draft_lesson_is_not_listed_for_students(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => 'دهم']);
        $this->lessonFor($tenant, [
            'status' => 'draft',
            'grade' => 'دهم',
            'lesson_title' => 'درس منتشرنشده',
        ]);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lessons")
            ->assertOk()
            ->assertDontSee('درس منتشرنشده');
    }

    /**
     * Staff preview. A consultant has no `student` session, so before the
     * reader was moved out of the `auth:student` group the «پیش‌نمایش» link
     * redirected to /student/login instead of opening the lesson — and a draft,
     * the one thing review exists for, could not be opened at all.
     */
    public function test_a_consultant_previews_a_draft_lesson(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $consultant = $this->consultantFor($tenant);

        $lesson = $this->lessonFor($tenant, ['status' => 'draft', 'grade' => 'دهم']);

        $this->actingAs($consultant, 'web')
            ->get("http://{$host}/student/lesson-reader/{$lesson->id}")
            ->assertOk()
            ->assertSee('student-lesson-reader');
    }

    public function test_a_staff_preview_never_writes_student_progress(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $consultant = $this->consultantFor($tenant);

        $lesson = $this->lessonFor($tenant, ['status' => 'published', 'grade' => 'دهم']);

        $this->actingAs($consultant, 'web')
            ->postJson("http://{$host}/student/lesson-reader/{$lesson->id}/progress", ['index' => 2])
            ->assertOk();

        $this->assertSame(0, LessonReadingProgress::query()
            ->where('lesson_id', $lesson->id)->count());
    }

    public function test_staff_cannot_preview_another_tenants_lesson(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $consultant = $this->consultantFor($tenant);

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->lessonFor($otherTenant, ['status' => 'published', 'grade' => 'دهم']);

        $this->actingAs($consultant, 'web')
            ->get("http://{$host}/student/lesson-reader/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_the_staff_landing_page_lists_the_authored_lessons(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $consultant = $this->consultantFor($tenant);

        $this->lessonFor($tenant, [
            'status' => 'published',
            'lesson_title' => 'درس فهرست‌شده',
        ]);

        $this->actingAs($consultant)
            ->get("http://{$host}/consultant/lessons")
            ->assertOk()
            ->assertSee('درس فهرست‌شده');
    }
}
