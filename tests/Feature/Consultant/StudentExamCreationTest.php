<?php

namespace Tests\Feature\Consultant;

use App\Models\Answer;
use App\Models\Domain;
use App\Models\Question;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the rebuilt create-exam dialog (consultant/students/exams.blade.php):
 * the five fixed lesson chips, the removal of question_count/total_marks from
 * the form, and store() deriving both server-side.
 *
 * Same strategy as StudentScheduleTest: DatabaseTransactions against the
 * provisioned schema (no RefreshDatabase — the base tables predate migrations).
 */
class StudentExamCreationTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{Tenant, User, Student} */
    private function tenantContext(string $host = 'tenant-a.test'): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        $user = User::factory()->for($tenant)->consultant()->create();
        $student = Student::factory()->for($tenant)->create();

        return [$tenant, $user, $student];
    }

    private function makeQuestion(int $tenantId, string $text, array $meta = []): Question
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

    public function test_create_dialog_offers_the_five_lesson_chips_and_drops_the_legacy_fields(): void
    {
        [, $consultant, $student] = $this->tenantContext();

        $response = $this->actingAs($consultant)
            ->get("http://tenant-a.test/consultant/students/{$student->id}/exams");

        $response->assertOk();

        foreach (['زیست‌شناسی', 'شیمی', 'فیزیک', 'ریاضی', 'زمین‌شناسی'] as $lesson) {
            $response->assertSee($lesson);
        }

        // New UI contract: picker popup trigger, end-time field, single submit.
        $response->assertSee('ثبت آزمون');
        $response->assertSee('انتخاب سوالات');
        $response->assertSee('ساعت پایان');
        $response->assertSee('name="lesson[]"', false);
        $response->assertSee('id="pick-questions-modal"', false);

        // Lesson/difficulty/corp filters live inside the picker popup, and the
        // lesson chips submit with the create form despite that.
        $response->assertSee('id="lesson-chips"', false);
        $response->assertSee('id="difficulty-chips"', false);
        $response->assertSee('id="corp-chips"', false);
        $response->assertSee('form="create-exam-form"', false);

        // Gone: two-step builder, raw-score and typed-count fields, other lessons.
        $response->assertDontSee('builder-steps');
        $response->assertDontSee('نمره از');
        $response->assertDontSee('تعداد سوال');
        $response->assertDontSee('name="question_count"', false);
        $response->assertDontSee('name="total_marks"', false);
        $response->assertDontSee('ادبیات فارسی');
        $response->assertDontSee('مرحله بعد');

        // Gone: duration read-out, the date/time input placeholders that
        // collided with the field icons, and the old dropdown difficulty filter.
        $response->assertDontSee('مدت آزمون');
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
        $q1 = $this->makeQuestion($tenant->id, 'سوال شیمی ۱');
        $q2 = $this->makeQuestion($tenant->id, 'سوال فیزیک ۲');

        $payload = [
            'title' => 'آزمون جامع شیمی و فیزیک',
            'exam_type' => 'comprehensive',
            'lesson' => ['شیمی', 'فیزیک'],
            'date' => '2030-05-20 08:30',
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
        $this->assertSame('20.00', (string) $test->total_marks);
        $this->assertSame(90, (int) $test->time_limit_minutes);

        $assignment = DB::table('student_assigned_quizzes')
            ->where('student_id', $student->id)
            ->where('test_id', $test->id)
            ->first();
        $this->assertNotNull($assignment);
        $this->assertSame('scheduled', $assignment->status);
        $this->assertSame('2030-05-20 08:30:00', $assignment->scheduled_at);

        $attached = DB::table('test_questions')->where('test_id', $test->id)->orderBy('position')->pluck('question_id')->all();
        $this->assertSame([$q1->id, $q2->id], array_map('intval', $attached));
    }

    public function test_lesson_must_be_one_of_the_five_allowed_chips(): void
    {
        [, $consultant, $student] = $this->tenantContext();

        $this->actingAs($consultant)
            ->post("http://tenant-a.test/consultant/students/{$student->id}/exams", [
                'title' => 'آزمون با درس ممنوعه',
                'exam_type' => 'quiz',
                'lesson' => ['ادبیات فارسی'],
                'date' => '2030-05-20 08:30',
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
    }

    public function test_bank_metadata_normalizes_lessons_companies_and_chapters(): void
    {
        $this->assertSame('زیست‌شناسی', \App\Support\QuestionBankMeta::subject('zist'));
        $this->assertNull(\App\Support\QuestionBankMeta::subject('adabiyat'));

        // ZWNJ/placement variants and OCR misspellings collapse to one canon.
        foreach (['قلمچی', 'قلم‌چی'] as $variant) {
            $this->assertSame('قلم‌چی', \App\Support\QuestionBankMeta::corp($variant));
        }
        foreach (['ماراثون', 'ماراتیون', 'مارآتون', 'ماراتون'] as $variant) {
            $this->assertSame('ماراتون', \App\Support\QuestionBankMeta::corp($variant));
        }
        $this->assertSame('دیاز', \App\Support\QuestionBankMeta::corp('دیاژ'));
        $this->assertNull(\App\Support\QuestionBankMeta::corp('   '));

        $this->assertSame('فصل 4 · دهم', \App\Support\QuestionBankMeta::chapterLabel('chapter4', '10'));
        $this->assertSame('فصل 1', \App\Support\QuestionBankMeta::chapterLabel('chapter1', null));
        $this->assertNull(\App\Support\QuestionBankMeta::chapterLabel('final-test', '10'));
    }
}
