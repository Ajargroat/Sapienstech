<?php

namespace Tests\Feature\Consultant;

use App\Models\Answer;
use App\Models\Domain;
use App\Models\Question;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\QuestionBankMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the rebuilt create-exam dialog (consultant/students/exams.blade.php):
 * the bank-derived lesson chips, the removal of question_count/total_marks from
 * the form, store() deriving both server-side, and the explicit start+end
 * window with its optional cover image (TASK AC1/AC8).
 *
 * Same strategy as StudentScheduleTest: RefreshDatabase against the
 * provisioned schema (no RefreshDatabase — the base tables predate migrations).
 */
class StudentExamCreationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Tenant, User, Student} */
    private function tenantContext(string $host = 'tenant-a.test'): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        $user = User::factory()->for($tenant)->consultant()->create();
        $student = Student::factory()->for($tenant)->create();

        return [$tenant, $user, $student];
    }

    private function makeQuestion(?int $tenantId, string $text, array $meta = []): Question
    {
        $question = Question::create([
            'tenant_id' => $tenantId,
            'question_text' => $text,
            'question_type' => 'multiple_choice',
            'difficulty' => 'Easy',
        ] + $meta);

        Answer::create([
            'tenant_id' => $tenantId,
            'question_id' => $question->id,
            'answer_text' => 'گزینهٔ صحیح',
            'is_correct' => true,
        ]);

        return $question;
    }

    /**
     * A minimal but genuinely valid PNG. UploadedFile::fake()->image() needs the
     * GD extension, which this environment may not have; the `image` rule only
     * inspects the content, so a real 1x1 PNG passes and keeps the test portable.
     */
    private function pngUpload(string $name = 'cover.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    public function test_create_dialog_offers_the_five_lesson_chips_and_drops_the_legacy_fields(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        foreach (['زیست‌شناسی', 'شیمی', 'فیزیک', 'ریاضی', 'زمین‌شناسی'] as $lesson) {
            $this->makeQuestion($tenant->id, "سوال {$lesson}", ['subject' => $lesson]);
        }

        $response = $this->actingAs($consultant)
            ->get("http://tenant-a.test/consultant/students/{$student->id}/exams");

        $response->assertOk();

        foreach (['زیست‌شناسی', 'شیمی', 'فیزیک', 'ریاضی', 'زمین‌شناسی'] as $lesson) {
            $response->assertSee($lesson);
        }

        // New UI contract: picker popup trigger, start + end window, single submit.
        $response->assertSee('ثبت آزمون');
        $response->assertSee('انتخاب سوالات');
        $response->assertSee('تاریخ پایان');
        $response->assertSee('ساعت پایان');
        $response->assertSee('name="lesson[]"', false);
        $response->assertSee('id="pick-questions-modal"', false);

        // Lesson/difficulty/corp/chapter filters live inside the picker popup, and
        // the lesson chips submit with the create form despite that.
        $response->assertSee('id="lesson-chips"', false);
        $response->assertSee('id="difficulty-chips"', false);
        $response->assertSee('id="corp-chips"', false);
        $response->assertSee('id="chapter-chips"', false);
        $response->assertSee('form="create-exam-form"', false);

        // The preview dialog is where question order is owned now.
        $response->assertSee('id="picker-preview"', false);
        $response->assertSee('id="preview-exam-modal"', false);

        // Rework run 3: the filters live in an in-dialog sidebar (dashboard
        // tab rail), the top bar keeps search + the selected count.
        $response->assertSee('class="picker-side"', false);
        $response->assertSee('id="picker-count"', false);
        $response->assertSee('data-picker-tab="0"', false);

        // MathJax 3 config + loader ride on the consultant layout head.
        $response->assertSee('cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js', false);
        $response->assertSee('window.MathJax', false);

        // The optional cover is a real file input on a multipart form.
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('name="cover"', false);

        // Gone: two-step builder, raw-score and typed-count fields, other lessons.
        $response->assertDontSee('builder-steps');
        $response->assertDontSee('نمره از');
        $response->assertDontSee('تعداد سوال');
        $response->assertDontSee('name="question_count"', false);
        $response->assertDontSee('name="total_marks"', false);
        $response->assertDontSee('ادبیات فارسی');
        $response->assertDontSee('مرحله بعد');

        // Gone: duration read-out, the old dropdown difficulty filter and the
        // date/time placeholders that collided with the field icons.
        $response->assertDontSee('duration-preview', false);
        $response->assertDontSee('id="picker-difficulty"', false);
        $response->assertDontSee('placeholder="۱۴۰۵/۰۶/۲۰"', false);
        $response->assertDontSee('placeholder="۰۸:۳۰"', false);
        $response->assertDontSee('placeholder="۱۰:۰۰"', false);
    }

    public function test_success_notification_renders_as_a_corner_toast(): void
    {
        [, $consultant, $student] = $this->tenantContext();

        $this->withSession(['success' => 'آزمون ساخته شد'])
            ->actingAs($consultant)
            ->get("http://tenant-a.test/consultant/students/{$student->id}/exams")
            ->assertOk()
            ->assertSee('exam-toast', false)
            ->assertSee('آزمون ساخته شد')
            ->assertDontSee('exam-flash', false);
    }

    public function test_consultant_can_create_an_exam_with_multiple_lessons(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q1 = $this->makeQuestion($tenant->id, 'سوال شیمی ۱', ['subject' => 'شیمی']);
        $q2 = $this->makeQuestion($tenant->id, 'سوال فیزیک ۲', ['subject' => 'فیزیک']);

        $payload = [
            'title' => 'آزمون جامع شیمی و فیزیک',
            'exam_type' => 'comprehensive',
            'lesson' => ['شیمی', 'فیزیک'],
            'date' => '2030-05-20 08:30',
            'end' => '2030-05-20 10:00',
            'date_jalali' => '۱۴۰۹/۰۲/۳۰',
            'time_limit_minutes' => 90,
            'questions' => [$q1->id, $q2->id],
            'description' => 'پوشش کامل دو درس',
        ];

        $this->actingAs($consultant)
            ->post("http://tenant-a.test/consultant/students/{$student->id}/exams", $payload)
            ->assertRedirect("http://tenant-a.test/consultant/students/{$student->id}/exams");

        $test = DB::table('tests')->where('test_title', $payload['title'])->first();
        $this->assertNotNull($test);
        $this->assertSame('شیمی، فیزیک', $test->lesson);
        // Both derived server-side now: count from the selection, marks fixed.
        $this->assertSame(2, (int) $test->question_count);
        // Numeric comparison on purpose: MySQL returns DECIMAL as '20.00'
        // while SQLite returns 20 — same value, different wire format.
        $this->assertEquals(20, (float) $test->total_marks);
        $this->assertSame(90, (int) $test->time_limit_minutes);

        $assignment = DB::table('student_assigned_quizzes')
            ->where('student_id', $student->id)
            ->where('test_id', $test->id)
            ->first();
        $this->assertNotNull($assignment);
        $this->assertSame('scheduled', $assignment->status);
        $this->assertSame('2030-05-20 08:30:00', $assignment->scheduled_at);
        $this->assertSame('2030-05-20 10:00:00', $assignment->ends_at);

        $attached = DB::table('test_questions')->where('test_id', $test->id)->orderBy('position')->pluck('question_id')->all();
        $this->assertSame([$q1->id, $q2->id], array_map('intval', $attached));
    }

    public function test_every_selected_question_survives_as_a_string_id(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();

        // The browser posts questions[] as strings; the old `->map('intval')`
        // passed the array key as intval()'s base, zeroing every id after the
        // first. Six questions must stay six, in the posted order.
        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            $ids[] = (string) $this->makeQuestion($tenant->id, "سوال {$i}", ['subject' => 'شیمی'])->id;
        }

        $this->actingAs($consultant)
            ->post("http://tenant-a.test/consultant/students/{$student->id}/exams", [
                'title' => 'آزمون شش‌سوالی',
                'exam_type' => 'quiz',
                'lesson' => ['شیمی'],
                'date' => '2030-05-20 08:30',
                'end' => '2030-05-20 10:00',
                'questions' => $ids,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $test = DB::table('tests')->where('test_title', 'آزمون شش‌سوالی')->first();
        $this->assertNotNull($test);
        $this->assertSame(6, (int) $test->question_count);

        $attached = DB::table('test_questions')
            ->where('test_id', $test->id)
            ->orderBy('position')
            ->pluck('question_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertSame(array_map('intval', $ids), $attached);
    }

    public function test_the_preview_renders_every_posted_question(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();

        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = (string) $this->makeQuestion($tenant->id, "پیش‌نمایش {$i}", ['subject' => 'شیمی'])->id;
        }

        // Same cast bug lived in preview(): it rendered only the first id.
        $query = http_build_query(['questions' => $ids]);
        $response = $this->actingAs($consultant)
            ->get("http://tenant-a.test/consultant/students/{$student->id}/exams/preview?{$query}");

        $response->assertOk();
        $this->assertSame(5, substr_count($response->getContent(), 'data-preview-id'));
    }

    public function test_an_uploaded_cover_is_stored_on_the_test(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q1 = $this->makeQuestion($tenant->id, 'سوال شیمی ۱', ['subject' => 'شیمی']);

        $this->actingAs($consultant)
            ->post("http://tenant-a.test/consultant/students/{$student->id}/exams", [
                'title' => 'آزمون با جلد',
                'exam_type' => 'quiz',
                'lesson' => ['شیمی'],
                'date' => '2030-05-20 08:30',
                'end' => '2030-05-20 09:30',
                'questions' => [$q1->id],
                'cover' => $this->pngUpload(),
            ])
            ->assertRedirect();

        $test = DB::table('tests')->where('test_title', 'آزمون با جلد')->first();
        $this->assertNotNull($test);
        $this->assertNotNull($test->cover_image_path);
        $this->assertStringContainsString('exam-covers/', $test->cover_image_path);
    }

    public function test_the_end_of_the_window_must_come_after_its_start(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q1 = $this->makeQuestion($tenant->id, 'سوال شیمی ۱', ['subject' => 'شیمی']);

        $this->actingAs($consultant)
            ->post("http://tenant-a.test/consultant/students/{$student->id}/exams", [
                'title' => 'آزمون با بازهٔ نامعتبر',
                'exam_type' => 'quiz',
                'lesson' => ['شیمی'],
                'date' => '2030-05-20 10:00',
                'end' => '2030-05-20 08:30',
                'questions' => [$q1->id],
            ])
            ->assertSessionHasErrors('end');

        $this->assertSame(0, DB::table('tests')->where('test_title', 'آزمون با بازهٔ نامعتبر')->count());
    }

    public function test_lesson_must_be_one_of_the_banks_subjects(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        // The bank only knows شیمی, so an off-bank lesson is rejected below
        // even though the old fixed list accepted it.
        $this->makeQuestion($tenant->id, 'سوال شیمی', ['subject' => 'شیمی']);

        $this->actingAs($consultant)
            ->post("http://tenant-a.test/consultant/students/{$student->id}/exams", [
                'title' => 'آزمون با درس ممنوعه',
                'exam_type' => 'quiz',
                'lesson' => ['ادبیات فارسی'],
                'date' => '2030-05-20 08:30',
                'end' => '2030-05-20 09:30',
            ])
            ->assertSessionHasErrors('lesson.0');

        $this->assertSame(0, DB::table('tests')->where('test_title', 'آزمون با درس ممنوعه')->count());
    }

    public function test_creating_an_exam_requires_at_least_one_lesson(): void
    {
        [, $consultant, $student] = $this->tenantContext();

        $this->actingAs($consultant)
            ->post("http://tenant-a.test/consultant/students/{$student->id}/exams", [
                'title' => 'آزمون بی‌درس',
                'exam_type' => 'quiz',
                'lesson' => [],
                'date' => '2030-05-20 08:30',
                'end' => '2030-05-20 09:30',
            ])
            ->assertSessionHasErrors('lesson');
    }

    public function test_question_bank_endpoint_serves_rich_cards_and_narrows_by_filters(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $this->makeQuestion($tenant->id, 'سوال نمونه بانک', [
            'subject' => 'شیمی', 'corp' => 'ماز', 'chapter_label' => 'فصل 2 · دهم',
        ]);
        $this->makeQuestion($tenant->id, 'سوال درس دیگر', ['subject' => 'فیزیک', 'corp' => 'گاج']);

        $url = "http://tenant-a.test/consultant/students/{$student->id}/exams/questions";

        // Matching lesson + corp: card shows the options and all four tags.
        $this->actingAs($consultant)
            ->get("{$url}?lessons[]=شیمی&corp=ماز&page=1")
            ->assertOk()
            ->assertSee('سوال نمونه بانک')
            ->assertDontSee('سوال درس دیگر')
            ->assertSee('گزینهٔ صحیح')
            ->assertSee('ماز')
            ->assertSee('فصل ۲ · دهم');

        // Non-matching lesson empties the list instead of leaking rows.
        $this->actingAs($consultant)
            ->get("{$url}?lessons[]=زیست‌شناسی&page=1")
            ->assertOk()
            ->assertDontSee('سوال نمونه بانک')
            ->assertDontSee('سوال درس دیگر');

        // Corp-only filter, sent with an OCR spelling variant, still merges.
        $this->actingAs($consultant)
            ->get("{$url}?corp=قلمچی&page=1")
            ->assertOk()
            ->assertDontSee('سوال نمونه بانک');

        // No filters at all: both rows come back.
        $this->actingAs($consultant)
            ->get("{$url}?page=1")
            ->assertOk()
            ->assertSee('سوال نمونه بانک')
            ->assertSee('سوال درس دیگر');

        // Chapter filter (bank-derived, TASK AC7) narrows to the matching row.
        // The card renders the label through persian_digits, so the raw ASCII
        // form is what the query takes and the Persian one is what comes back.
        $this->actingAs($consultant)
            ->get("{$url}?chapter=".urlencode('فصل 2 · دهم').'&page=1')
            ->assertOk()
            ->assertSee('سوال نمونه بانک')
            ->assertSee('فصل ۲ · دهم')
            ->assertDontSee('سوال درس دیگر');
    }

    public function test_bank_metadata_normalizes_lessons_companies_and_chapters(): void
    {
        $this->assertSame('زیست‌شناسی', QuestionBankMeta::subject('zist'));
        $this->assertNull(QuestionBankMeta::subject('adabiyat'));

        // ZWNJ/placement variants and OCR misspellings collapse to one canon.
        foreach (['قلمچی', 'قلم‌چی'] as $variant) {
            $this->assertSame('قلم‌چی', QuestionBankMeta::corp($variant));
        }
        foreach (['ماراثون', 'ماراتیون', 'مارآتون', 'ماراتون'] as $variant) {
            $this->assertSame('ماراتون', QuestionBankMeta::corp($variant));
        }
        $this->assertSame('دیاز', QuestionBankMeta::corp('دیاژ'));
        $this->assertNull(QuestionBankMeta::corp('   '));

        $this->assertSame('فصل 4 · دهم', QuestionBankMeta::chapterLabel('chapter4', '10'));
        $this->assertSame('فصل 1', QuestionBankMeta::chapterLabel('chapter1', null));
        $this->assertNull(QuestionBankMeta::chapterLabel('final-test', '10'));
    }
}
