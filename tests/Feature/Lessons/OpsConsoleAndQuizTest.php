<?php

namespace Tests\Feature\Lessons;

use App\Models\Answer;
use App\Models\BookLesson;
use App\Models\Domain;
use App\Models\LessonAiDraft;
use App\Models\LessonImport;
use App\Models\Question;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\LessonPdf\ExtractionResult;
use App\Support\LessonPdf\PdfExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The two halves of the last correction.
 *
 * 1. **The pipeline is ours.** Uploading/extracting a book lives in /ops, is
 *    closed to every tenant role, and runs IN THE REQUEST — because the queue
 *    version left an import on `queued` forever whenever no worker happened to
 *    be running, while the page promised it would refresh itself.
 * 2. **The reader has the demo's main feature.** The approved demo ends with
 *    «خودتان را بسنجید»: released questions, answered in place. The reader had
 *    none, so the AI half of the feature produced questions nobody could see.
 *    The answer key must not be in the markup (the demo printed it because the
 *    demo was static).
 */
class OpsConsoleAndQuizTest extends TestCase
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

    private function opsUserFor(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        $user = User::factory()->consultant()->create(['tenant_id' => $tenant->id]);
        $user->forceFill(['is_platform_admin' => true])->save();

        return $user;
    }

    private function lessonFor(Tenant $tenant, array $attributes = []): BookLesson
    {
        app()->instance('tenant', $tenant);

        $import = LessonImport::create([
            'book_title' => 'زیست‌شناسی دهم',
            'pdf_path' => 'lessons/pdf/book.pdf',
            'status' => 'ready',
        ]);

        return BookLesson::create($attributes + [
            'lesson_import_id' => $import->id,
            'book_title' => 'زیست‌شناسی دهم',
            'lesson_title' => 'فتوسنتز',
            'status' => 'draft',
        ]);
    }

    /** One approved-and-released AI question, linked to the lesson itself. */
    private function releasedQuestionFor(BookLesson $lesson, string $stem = 'کدام گاز جذب می‌شود؟'): Question
    {
        $question = Question::create([
            'tenant_id' => null,
            'lesson_id' => $lesson->id,
            'question_text' => $stem,
            'question_type' => 'multiple_choice',
            'difficulty' => 'medium',
            'solution_text' => 'گیاه دی‌اکسید کربن جذب می‌کند.',
        ]);

        $question->answers()->create(['tenant_id' => null, 'answer_text' => 'اکسیژن', 'is_correct' => false]);
        $question->answers()->create(['tenant_id' => null, 'answer_text' => 'دی‌اکسید کربن', 'is_correct' => true]);

        LessonAiDraft::create([
            'tenant_id' => $lesson->tenant_id,
            'lesson_id' => $lesson->id,
            'kind' => LessonAiDraft::KIND_QUESTIONS,
            'status' => 'released',
            'released_question_id' => $question->id,
            'released_count' => 1,
        ]);

        return $question->fresh();
    }

    /* ------------------------------------------------------------- the ops gate */

    public function test_the_upload_route_no_longer_exists_in_a_tenant_panel(): void
    {
        $this->assertNull(
            Route::getRoutes()->getByName('consultant.lessons.store'),
            'Uploading a book must not be a tenant action: it lives in /ops.'
        );

        $this->assertNull(Route::getRoutes()->getByName('teacher.lessons.store'));
        $this->assertNull(Route::getRoutes()->getByName('consultant.lessons.import.show'));
    }

    public function test_a_tenant_staff_member_cannot_open_the_ops_console(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $consultant = User::factory()->consultant()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($consultant)
            ->get("http://{$host}/ops/lessons")
            ->assertNotFound();
    }

    public function test_a_guest_is_sent_to_login_from_the_ops_console(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();

        $this->get("http://{$host}/ops/lessons")->assertRedirect();
    }

    public function test_an_ops_account_opens_the_console(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->opsUserFor($tenant);

        $this->actingAs($user)
            ->get("http://{$host}/ops/lessons")
            ->assertOk()
            ->assertSee('کنسول عملیات');
    }

    /* --------------------------------------------------- extraction runs inline */

    public function test_extraction_happens_in_the_request_and_never_leaves_a_row_queued(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->opsUserFor($tenant);

        $import = LessonImport::create([
            'book_title' => 'کتاب بدون فایل',
            'pdf_path' => 'lessons/pdf/does-not-exist.pdf',
            'status' => 'queued',
        ]);

        // A stub extractor: this test is about WHERE the work runs, not about
        // what python produces (the pipeline tests cover that).
        $this->app->bind(PdfExtractor::class, fn () => new class implements PdfExtractor
        {
            public function extract(string $pdfAbsolutePath, string $pagesDir, string $figuresDir): ExtractionResult
            {
                return new ExtractionResult(error: 'pdf_missing');
            }
        });

        $this->actingAs($user)
            ->post("http://{$host}/ops/lessons/{$import->id}/extract")
            ->assertRedirect();

        $import->refresh();

        // The whole point: by the time the request returns, the row is terminal.
        $this->assertNotSame('queued', $import->status);
        $this->assertSame('failed', $import->status);
        $this->assertNotNull($import->finished_at);
    }

    /* -------------------------------------------------------------- the quiz */

    public function test_a_released_question_appears_in_the_reader(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => null]);
        $lesson = $this->lessonFor($tenant, ['status' => 'published']);
        $question = $this->releasedQuestionFor($lesson);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lesson-reader/{$lesson->id}")
            ->assertOk()
            ->assertSee('خودتان را بسنجید')
            ->assertSee($question->question_text)
            ->assertSee('ثبت پاسخ');
    }

    public function test_the_answer_key_is_not_printed_into_the_page(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => null]);
        $lesson = $this->lessonFor($tenant, ['status' => 'published']);
        $this->releasedQuestionFor($lesson);

        $html = $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lesson-reader/{$lesson->id}")
            ->assertOk()
            ->getContent();

        // The demo marked the correct option with `good` because it was static;
        // a live reader must tell nobody which option wins until they answer.
        $this->assertStringNotContainsString('is-correct', (string) $html);
        $this->assertStringNotContainsString('is_correct', (string) $html);
    }

    public function test_answering_is_graded_on_the_server(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => null]);
        $lesson = $this->lessonFor($tenant, ['status' => 'published']);
        $question = $this->releasedQuestionFor($lesson);

        $wrong = $question->answers->firstWhere('is_correct', false);
        $right = $question->answers->firstWhere('is_correct', true);

        $this->actingAs($student, 'student')
            ->postJson("http://{$host}/student/lesson-reader/{$lesson->id}/answer", [
                'question' => $question->id,
                'answer' => $wrong->id,
            ])
            ->assertOk()
            ->assertJson([
                'correct' => false,
                'correct_answer' => $right->id,
                'explanation' => 'گیاه دی‌اکسید کربن جذب می‌کند.',
            ]);

        $this->actingAs($student, 'student')
            ->postJson("http://{$host}/student/lesson-reader/{$lesson->id}/answer", [
                'question' => $question->id,
                'answer' => $right->id,
            ])
            ->assertOk()
            ->assertJson(['correct' => true]);
    }

    public function test_a_question_released_for_another_lesson_cannot_be_answered_here(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => null]);
        $lesson = $this->lessonFor($tenant, ['status' => 'published']);
        $other = $this->lessonFor($tenant, ['status' => 'published', 'lesson_title' => 'درس دیگر']);

        $foreign = $this->releasedQuestionFor($other, 'سؤال درس دیگر');
        $option = $foreign->answers->first();

        // Ownership is proven through the draft, so a shared-bank question from
        // a sibling lesson is a 404 — not a 200 that grades it anyway.
        $this->actingAs($student, 'student')
            ->postJson("http://{$host}/student/lesson-reader/{$lesson->id}/answer", [
                'question' => $foreign->id,
                'answer' => $option->id,
            ])
            ->assertNotFound();
    }

    public function test_a_draft_lesson_without_questions_shows_no_quiz_section(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => null]);
        $lesson = $this->lessonFor($tenant, ['status' => 'published']);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lesson-reader/{$lesson->id}")
            ->assertOk()
            ->assertDontSee('خودتان را بسنجید');
    }

    /* --------------------------------------------- the chain that reaches a student */

    public function test_publishing_is_what_puts_a_lesson_in_front_of_a_student(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->opsUserFor($tenant);

        $lesson = $this->lessonFor($tenant, ['status' => 'draft', 'grade' => 'یازدهم']);

        // A draft is invisible, and that is the state the console used to stop
        // at: extraction, a lesson, and no student anywhere.
        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => 'یازدهم']);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lesson-reader/{$lesson->id}")
            ->assertNotFound();

        $this->actingAs($user)
            ->post("http://{$host}/ops/lessons/{$lesson->id}/publish", [
                'lesson_title' => 'فتوسنتز',
                'grade' => 'یازدهم',
            ])
            ->assertRedirect();

        $this->assertSame('published', $lesson->fresh()->status);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lesson-reader/{$lesson->id}")
            ->assertOk()
            ->assertSee('فتوسنتز');

        // The library is where a student actually finds it.
        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lessons")
            ->assertOk()
            ->assertSee('فتوسنتز');
    }

    public function test_a_published_lesson_stays_invisible_to_another_grade(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->opsUserFor($tenant);

        $lesson = $this->lessonFor($tenant, ['status' => 'draft', 'grade' => null]);

        $this->actingAs($user)
            ->post("http://{$host}/ops/lessons/{$lesson->id}/publish", [
                'lesson_title' => 'فتوسنتز',
                'grade' => 'دهم',
            ])
            ->assertRedirect();

        $other = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => 'یازدهم']);

        $this->actingAs($other, 'student')
            ->get("http://{$host}/student/lesson-reader/{$lesson->id}")
            ->assertNotFound();
    }

    public function test_unpublishing_takes_the_lesson_back_from_students(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->opsUserFor($tenant);

        $lesson = $this->lessonFor($tenant, ['status' => 'published', 'grade' => null]);
        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => null]);

        $this->actingAs($user)
            ->post("http://{$host}/ops/lessons/{$lesson->id}/unpublish")
            ->assertRedirect();

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lesson-reader/{$lesson->id}")
            ->assertNotFound();
    }

    public function test_the_ops_lesson_page_says_what_the_student_will_see(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->opsUserFor($tenant);
        $lesson = $this->lessonFor($tenant, ['status' => 'draft', 'grade' => null]);

        $this->actingAs($user)
            ->get("http://{$host}/ops/lessons/{$lesson->id}/manage")
            ->assertOk()
            ->assertSee('دانش‌آموز چه چیزی می‌بیند؟')
            ->assertSee('پیش‌نویس')
            // The path, not `route()`: outside a request the helper builds the
            // URL from APP_URL, so the absolute form would assert the wrong host.
            ->assertSee('/student/lesson-reader/'.$lesson->id, false);
    }

    public function test_one_release_writes_every_question_of_the_draft(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->opsUserFor($tenant);

        $lesson = $this->lessonFor($tenant, ['status' => 'published']);

        $draft = LessonAiDraft::create([
            'tenant_id' => $tenant->id,
            'lesson_id' => $lesson->id,
            'kind' => LessonAiDraft::KIND_QUESTIONS,
            'status' => 'ready',
            'payload' => ['questions' => [
                $this->draftQuestion('پرسش یک'),
                $this->draftQuestion('پرسش دو'),
                $this->draftQuestion('پرسش سه'),
            ]],
        ]);

        $this->actingAs($user)
            ->post("http://{$host}/ops/lessons/{$lesson->id}/release", ['draft' => $draft->id])
            ->assertRedirect();

        $draft->refresh();

        // The old single `released_question_id` wrote ONE question per draft and
        // then refused the rest -- the demo shows three, so the page was
        // permanently one question short of the template it was built from.
        $this->assertSame(3, (int) $draft->released_count);
        $this->assertSame('released', $draft->status);
        $this->assertSame(3, Question::where('lesson_id', $lesson->id)->count());

        // ...and they reach the student, in order, with the key withheld.
        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => null]);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/lesson-reader/{$lesson->id}")
            ->assertOk()
            ->assertSee('پرسش یک')
            ->assertSee('پرسش دو')
            ->assertSee('پرسش سه')
            ->assertSee('۳ پرسش');
    }

    /** @return array<string, mixed> */
    private function draftQuestion(string $stem): array
    {
        return [
            'stem' => $stem,
            'difficulty' => 'medium',
            'explanation' => 'توضیح '.$stem,
            'options' => [
                ['text' => 'گزینهٔ الف', 'is_correct' => true],
                ['text' => 'گزینهٔ ب', 'is_correct' => false],
            ],
        ];
    }
}
