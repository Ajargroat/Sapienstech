<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use App\Models\Domain;
use App\Models\LessonMaterial;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The full teacher↔student flows: uploading lesson materials and downloading
 * them from the student side, the assignment status lifecycle
 * (pending → submitted → completed), and the class timetable.
 */
class TeacherStudentFlowTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'flow.test';

    private Tenant $tenant;

    private User $teacher;

    private function setUpTenant(): void
    {
        $this->tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $this->tenant->id, 'domain' => self::HOST, 'is_primary' => true]);
        Cache::forget('domain:'.self::HOST);

        app()->instance('tenant', $this->tenant);
        $this->teacher = User::factory()->teacher()->create(['tenant_id' => $this->tenant->id]);
    }

    private function student(string $grade = 'هفتم'): Student
    {
        app()->instance('tenant', $this->tenant);

        return Student::factory()->for($this->tenant)->create(['grade' => $grade]);
    }

    private function url(string $path): string
    {
        return 'http://'.self::HOST.$path;
    }

    // ------------------------------------------------------------------
    // Lesson materials
    // ------------------------------------------------------------------

    public function test_teacher_uploads_material_and_student_downloads_it(): void
    {
        $this->setUpTenant();
        $student = $this->student('هفتم');

        $file = UploadedFile::fake()->create('geometry-lesson.pdf', 120, 'application/pdf');

        $this->actingAs($this->teacher)
            ->post($this->url('/teacher/materials'), [
                'title' => 'جزوه هندسه فصل ۱',
                'subject' => 'ریاضی',
                'grade' => 'هفتم',
                'file' => $file,
                'is_published' => '1',
            ])
            ->assertRedirect(route('teacher.materials.index'));

        $material = LessonMaterial::query()->firstOrFail();
        $this->assertSame('جزوه هندسه فصل ۱', $material->title);
        $this->assertSame($this->teacher->id, $material->teacher_id);
        $this->assertSame($this->tenant->id, $material->tenant_id);
        $this->assertTrue(str_starts_with($material->file_path, 'materials/'));

        // The file physically exists under the tenant's own tree.
        $this->assertFileExists(public_path("tenants/{$this->tenant->slug}/".$material->file_path));

        // Listed on the teacher's library page.
        $this->actingAs($this->teacher)
            ->get($this->url('/teacher/materials'))
            ->assertOk()
            ->assertSee('جزوه هندسه فصل ۱');

        // The matching-grade student sees it in the lessons library.
        $this->actingAs($student, 'student')
            ->get($this->url('/student/lessons'))
            ->assertOk()
            ->assertSee('جزوه هندسه فصل ۱');

        // …and can download it: counter bumps, file streams.
        $this->actingAs($student, 'student')
            ->get($this->url("/student/lessons/{$material->id}/download"))
            ->assertOk();

        $this->assertSame(1, $material->fresh()->download_count);

        TenantUploads::delete($material->file_path);
    }

    public function test_material_of_another_grade_is_hidden_from_the_student(): void
    {
        $this->setUpTenant();
        $student = $this->student('هشتم');

        $material = LessonMaterial::query()->create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'جزوه مخصوص نهم',
            'file_path' => 'materials/hidden.pdf',
            'file_name' => 'hidden.pdf',
            'grade' => 'نهم',
        ]);

        $this->actingAs($student, 'student')
            ->get($this->url('/student/lessons'))
            ->assertOk()
            ->assertDontSee('جزوه مخصوص نهم');

        // Direct download attempt: blocked.
        $this->actingAs($student, 'student')
            ->get($this->url("/student/lessons/{$material->id}/download"))
            ->assertNotFound();
    }

    public function test_teacher_cannot_download_or_delete_another_teachers_material(): void
    {
        $this->setUpTenant();

        $otherTeacher = User::factory()->teacher()->create(['tenant_id' => $this->tenant->id]);
        $material = LessonMaterial::query()->create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'جزوه همکار',
            'file_path' => 'materials/colleague.pdf',
            'file_name' => 'colleague.pdf',
        ]);

        $this->actingAs($this->teacher)
            ->get($this->url("/teacher/materials/{$material->id}/download"))
            ->assertNotFound();

        $this->actingAs($this->teacher)
            ->delete($this->url("/teacher/materials/{$material->id}"))
            ->assertNotFound();

        $this->assertDatabaseHas('lesson_materials', ['id' => $material->id]);
    }

    public function test_upload_requires_a_file(): void
    {
        $this->setUpTenant();

        $this->actingAs($this->teacher)
            ->post($this->url('/teacher/materials'), [
                'title' => 'بدون فایل',
            ])
            ->assertSessionHasErrors(['file']);

        $this->assertSame(0, LessonMaterial::query()->count());
    }

    // ------------------------------------------------------------------
    // Assignments + statuses
    // ------------------------------------------------------------------

    public function test_assignment_lifecycle_pending_to_submitted_to_completed(): void
    {
        $this->setUpTenant();
        $student = $this->student('هفتم');

        $this->actingAs($this->teacher)
            ->post($this->url('/teacher/assignments'), [
                'title' => 'تمارین صفحه ۲۰',
                'subject' => 'ریاضی',
                'grade' => 'هفتم',
                'due_at' => now()->addWeek()->toDateString(),
                'max_score' => '20',
                'is_published' => '1',
            ])
            ->assertRedirect(route('teacher.assignments.index'));

        $assignment = Assignment::query()->firstOrFail();
        $this->assertSame('هفتم', $assignment->grade);

        // No pivot row yet: the student's status is implicitly pending.
        $this->assertSame('pending', $assignment->statusFor($student->id));

        // Student sees it with the pending pill.
        $this->actingAs($student, 'student')
            ->get($this->url('/student/assignments'))
            ->assertOk()
            ->assertSee('تمارین صفحه ۲۰')
            ->assertSee('در انتظار انجام');

        // Student submits.
        $this->actingAs($student, 'student')
            ->post($this->url("/student/assignments/{$assignment->id}/submit"), [
                'note' => 'در دفتر کار نوشتم',
            ])
            ->assertRedirect(route('student.assignments.show', $assignment));

        $this->assertSame('submitted', $assignment->fresh()->statusFor($student->id));

        // Teacher's status board shows the submission, then acknowledges it.
        $this->actingAs($this->teacher)
            ->get($this->url("/teacher/assignments/{$assignment->id}"))
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee('تحویل‌شده');

        $this->actingAs($this->teacher)
            ->patch($this->url("/teacher/assignments/{$assignment->id}/students/{$student->id}"), [
                'status' => 'completed',
                'score' => '18.5',
            ])
            ->assertRedirect(route('teacher.assignments.show', $assignment));

        $submission = $assignment->submissions()->firstOrFail();
        $this->assertSame('completed', $submission->status);
        $this->assertNotNull($submission->submitted_at);

        // The completed status is final on the student side.
        $this->actingAs($student, 'student')
            ->post($this->url("/student/assignments/{$assignment->id}/submit"))
            ->assertSessionHas('error');
    }

    public function test_assignment_targets_only_its_grade(): void
    {
        $this->setUpTenant();
        $studentSeventh = $this->student('هفتم');
        $studentNinth = $this->student('نهم');

        $assignment = Assignment::query()->create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'تکلیف ویژه هفتم',
            'grade' => 'هفتم',
        ]);

        $this->actingAs($studentSeventh, 'student')
            ->get($this->url('/student/assignments'))
            ->assertOk()
            ->assertSee('تکلیف ویژه هفتم');

        $this->actingAs($studentNinth, 'student')
            ->get($this->url('/student/assignments'))
            ->assertOk()
            ->assertDontSee('تکلیف ویژه هفتم');

        $this->actingAs($studentNinth, 'student')
            ->get($this->url("/student/assignments/{$assignment->id}"))
            ->assertNotFound();
    }

    public function test_new_assignment_requires_a_grade(): void
    {
        $this->setUpTenant();

        $this->actingAs($this->teacher)
            ->post($this->url('/teacher/assignments'), [
                'title' => 'تکلیف بدون پایه',
            ])
            ->assertSessionHasErrors(['grade']);

        $this->assertSame(0, Assignment::query()->count());
    }

    public function test_assignment_can_be_narrowed_to_one_classroom(): void
    {
        $this->setUpTenant();
        $classroom = Classroom::create(['tenant_id' => $this->tenant->id, 'grade' => 'هفتم', 'name' => '۷۱']);
        $inClass = Student::factory()->for($this->tenant)->create(['grade' => 'هفتم']);
        $gradeOnly = Student::factory()->for($this->tenant)->create(['grade' => 'هفتم']);
        $classroom->students()->attach($inClass->id, ['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->teacher)
            ->post($this->url('/teacher/assignments'), [
                'title' => 'تکلیف کلاس ۷۱',
                'grade' => 'هفتم',
                'classroom_id' => $classroom->id,
                'is_published' => '1',
            ])
            ->assertRedirect(route('teacher.assignments.index'));

        $assignment = Assignment::query()->firstOrFail();
        $this->assertSame($classroom->id, $assignment->classroom_id);

        // The dialog is rendered inline on the list page, carrying the
        // grade-grouped classroom options for its picker.
        $this->actingAs($this->teacher)
            ->get($this->url('/teacher/assignments'))
            ->assertOk()
            ->assertSee('data-assignment-dialog', false)
            ->assertSee('data-classroom-options', false);

        // Only the classroom's own roster is on the board.
        $this->actingAs($this->teacher)
            ->get($this->url("/teacher/assignments/{$assignment->id}"))
            ->assertOk()
            ->assertSee($inClass->name)
            ->assertDontSee($gradeOnly->name);

        // ……and only its students see the task.
        $this->actingAs($inClass, 'student')
            ->get($this->url('/student/assignments'))
            ->assertOk()
            ->assertSee('تکلیف کلاس ۷۱');

        $this->actingAs($gradeOnly, 'student')
            ->get($this->url('/student/assignments'))
            ->assertOk()
            ->assertDontSee('تکلیف کلاس ۷۱');

        $this->actingAs($gradeOnly, 'student')
            ->get($this->url("/student/assignments/{$assignment->id}"))
            ->assertNotFound();
    }

    public function test_classroom_of_another_grade_is_rejected(): void
    {
        $this->setUpTenant();
        $classroom = Classroom::create(['tenant_id' => $this->tenant->id, 'grade' => 'نهم', 'name' => '۹۱']);

        $this->actingAs($this->teacher)
            ->post($this->url('/teacher/assignments'), [
                'title' => 'ناسازگار',
                'grade' => 'هفتم',
                'classroom_id' => $classroom->id,
            ])
            ->assertSessionHasErrors(['classroom_id']);

        $this->assertSame(0, Assignment::query()->count());
    }

    // ------------------------------------------------------------------
    // Assignment attachments
    // ------------------------------------------------------------------

    public function test_teacher_attachment_rides_along_to_the_student(): void
    {
        $this->setUpTenant();
        $student = $this->student('هفتم');

        $this->actingAs($this->teacher)
            ->post($this->url('/teacher/assignments'), [
                'title' => 'کاربرگ ریاضی',
                'grade' => 'هفتم',
                'file' => UploadedFile::fake()->create('worksheet.pdf', 40, 'application/pdf'),
                'is_published' => '1',
            ])
            ->assertRedirect(route('teacher.assignments.index'));

        $assignment = Assignment::query()->firstOrFail();
        $this->assertTrue(str_starts_with($assignment->file_path, 'assignments/'));
        $this->assertFileExists(public_path("tenants/{$this->tenant->slug}/".$assignment->file_path));
        $this->assertFalse($assignment->isImageFile());

        // The student sees the brief's attachment on the detail page.
        $this->actingAs($student, 'student')
            ->get($this->url("/student/assignments/{$assignment->id}"))
            ->assertOk()
            ->assertSee('فایل پیوست تکلیف');

        TenantUploads::delete($assignment->file_path);
    }

    public function test_student_submits_a_photo_and_the_teacher_sees_a_thumbnail(): void
    {
        $this->setUpTenant();
        $student = $this->student('هفتم');

        $assignment = Assignment::query()->create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'تمرین صفحه ۱۲',
            'grade' => 'هفتم',
        ]);

        $this->actingAs($student, 'student')
            ->post($this->url("/student/assignments/{$assignment->id}/submit"), [
                'note' => 'عکس دفترم',
                'file' => UploadedFile::fake()->create('work.jpg', 120, 'image/jpeg'),
            ])
            ->assertRedirect(route('student.assignments.show', $assignment));

        $submission = $assignment->submissions()->firstOrFail();
        $this->assertSame('submitted', $submission->status);
        $this->assertTrue(str_starts_with($submission->file_path, 'assignments/'));
        $this->assertTrue($submission->isImageFile());
        $this->assertFileExists(public_path("tenants/{$this->tenant->slug}/".$submission->file_path));

        // The board renders the photo as an inline thumbnail link.
        $this->actingAs($this->teacher)
            ->get($this->url("/teacher/assignments/{$assignment->id}"))
            ->assertOk()
            ->assertSee($submission->fileUrl(), false);

        TenantUploads::delete($submission->file_path);
    }

    public function test_resubmitting_replaces_the_previous_upload(): void
    {
        $this->setUpTenant();
        $student = $this->student('هفتم');

        $assignment = Assignment::query()->create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'تمرین دوباره',
            'grade' => 'هفتم',
        ]);

        $this->actingAs($student, 'student')
            ->post($this->url("/student/assignments/{$assignment->id}/submit"), [
                'file' => UploadedFile::fake()->create('first.jpg', 120, 'image/jpeg'),
            ]);

        $first = $assignment->submissions()->firstOrFail()->file_path;
        $this->assertFileExists(public_path("tenants/{$this->tenant->slug}/{$first}"));

        $this->actingAs($student, 'student')
            ->post($this->url("/student/assignments/{$assignment->id}/submit"), [
                'file' => UploadedFile::fake()->create('second.jpg', 120, 'image/jpeg'),
            ]);

        $second = $assignment->fresh()->submissions()->firstOrFail()->file_path;
        $this->assertNotSame($first, $second);
        $this->assertFileExists(public_path("tenants/{$this->tenant->slug}/{$second}"));

        // The superseded upload is gone from disk, not merely orphaned.
        $this->assertFileDoesNotExist(public_path("tenants/{$this->tenant->slug}/{$first}"));

        TenantUploads::delete($second);
    }

    public function test_an_upload_of_the_wrong_type_is_rejected(): void
    {
        $this->setUpTenant();

        $this->actingAs($this->teacher)
            ->post($this->url('/teacher/assignments'), [
                'title' => 'پیوست نامعتبر',
                'grade' => 'هفتم',
                'file' => UploadedFile::fake()->create('notes.zip', 10, 'application/zip'),
            ])
            ->assertSessionHasErrors(['file']);

        $this->assertSame(0, Assignment::query()->count());
    }

    public function test_deleting_an_assignment_clears_stored_attachments(): void
    {
        $this->setUpTenant();
        $student = $this->student('هفتم');

        $this->actingAs($this->teacher)
            ->post($this->url('/teacher/assignments'), [
                'title' => 'با پیوست',
                'grade' => 'هفتم',
                'file' => UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'),
                'is_published' => '1',
            ]);

        $assignment = Assignment::query()->firstOrFail();

        $this->actingAs($student, 'student')
            ->post($this->url("/student/assignments/{$assignment->id}/submit"), [
                'file' => UploadedFile::fake()->create('handin.jpg', 120, 'image/jpeg'),
            ]);

        $brief = $assignment->file_path;
        $handin = $assignment->fresh()->submissions()->firstOrFail()->file_path;

        $this->actingAs($this->teacher)
            ->delete($this->url("/teacher/assignments/{$assignment->id}"))
            ->assertRedirect(route('teacher.assignments.index'));

        // Both halves are removed from disk with the row.
        $this->assertFileDoesNotExist(public_path("tenants/{$this->tenant->slug}/{$brief}"));
        $this->assertFileDoesNotExist(public_path("tenants/{$this->tenant->slug}/{$handin}"));
        $this->assertSame(0, Assignment::query()->count());
    }

    public function test_teacher_cannot_touch_another_teachers_assignment(): void
    {
        $this->setUpTenant();

        $otherTeacher = User::factory()->teacher()->create(['tenant_id' => $this->tenant->id]);
        $assignment = Assignment::query()->create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'تکلیف همکار',
        ]);

        $this->actingAs($this->teacher)
            ->get($this->url("/teacher/assignments/{$assignment->id}"))
            ->assertNotFound();

        $this->actingAs($this->teacher)
            ->delete($this->url("/teacher/assignments/{$assignment->id}"))
            ->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Class schedule / timetable
    // ------------------------------------------------------------------

    public function test_class_schedule_reaches_the_student_timetable_by_grade(): void
    {
        $this->setUpTenant();
        $student = $this->student('هفتم');

        $this->actingAs($this->teacher)
            ->post($this->url('/teacher/schedule'), [
                'title' => 'ریاضی کلاس ۷۱',
                'subject' => 'ریاضی',
                'grade' => 'هفتم',
                'day_of_week' => '0',
                'start_time' => '08:00',
                'end_time' => '09:30',
                'room' => 'کلاس ۲',
                'color' => '#06B6D4',
                'is_published' => '1',
            ])
            ->assertRedirect(route('teacher.schedule.index'));

        $item = ClassSchedule::query()->firstOrFail();
        $this->assertSame(0, $item->day_of_week);

        // Teacher sees it on their own schedule page.
        $this->actingAs($this->teacher)
            ->get($this->url('/teacher/schedule'))
            ->assertOk()
            ->assertSee('ریاضی کلاس ۷۱');

        // Same-grade student sees it on the timetable…
        $this->actingAs($student, 'student')
            ->get($this->url('/student/timetable'))
            ->assertOk()
            ->assertSee('ریاضی کلاس ۷۱');

        // …a different grade does not.
        $other = $this->student('نهم');
        $this->actingAs($other, 'student')
            ->get($this->url('/student/timetable'))
            ->assertOk()
            ->assertDontSee('ریاضی کلاس ۷۱');

        // Editing works (PUT) and validation rejects an inverted time range.
        $this->actingAs($this->teacher)
            ->put($this->url("/teacher/schedule/{$item->id}"), [
                'title' => 'ریاضی کلاس ۷۱ (ویراسته)',
                'day_of_week' => '0',
                'start_time' => '09:00',
                'end_time' => '10:30',
            ])
            ->assertRedirect(route('teacher.schedule.index'));

        $this->assertSame('ریاضی کلاس ۷۱ (ویراسته)', $item->fresh()->title);

        $this->actingAs($this->teacher)
            ->post($this->url('/teacher/schedule'), [
                'title' => 'زنگ معکوس',
                'day_of_week' => '1',
                'start_time' => '10:00',
                'end_time' => '08:00',
            ])
            ->assertSessionHasErrors(['end_time']);
    }

    public function test_student_pages_require_student_authentication(): void
    {
        $this->setUpTenant();

        foreach (['/student/lessons', '/student/assignments', '/student/timetable'] as $path) {
            $this->get($this->url($path))
                ->assertRedirect(route('student.login'));
        }
    }
}
