<?php

namespace Tests\Feature\Consultant;

use App\Models\Answer;
use App\Models\AttemptAnswer;
use App\Models\Domain;
use App\Models\Question;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use App\Models\StudentTestAttempt;
use App\Models\Tenant;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The consultant's exam pages are read-only (TASK AC3): the workspace cards
 * show the derived count and the live window, the review page shows the
 * correct answers but cannot create an attempt, and the result page renders
 * the same contract the student sees.
 */
class StudentExamReviewTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Tenant, User, Student} */
    private function tenantContext(string $host = 'review.test'): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        $consultant = User::factory()->for($tenant)->consultant()->create();
        $student = Student::factory()->for($tenant)->create();

        return [$tenant, $consultant, $student];
    }

    private function question(Tenant $tenant, string $text, string $subject = 'شیمی'): Question
    {
        $question = Question::create([
            'tenant_id' => $tenant->id,
            'question_text' => $text,
            'question_type' => 'multiple_choice',
            'difficulty' => 'Easy',
            'subject' => $subject,
        ]);

        Answer::create([
            'tenant_id' => $tenant->id,
            'question_id' => $question->id,
            'answer_text' => 'گزینهٔ صحیح',
            'is_correct' => true,
        ]);
        Answer::create([
            'tenant_id' => $tenant->id,
            'question_id' => $question->id,
            'answer_text' => 'گزینهٔ نادرست',
            'is_correct' => false,
        ]);

        return $question;
    }

    /**
     * @param  array<int, Question>  $questions
     */
    private function exam(Tenant $tenant, User $consultant, Student $student, array $questions): StudentAssignedQuiz
    {
        $test = new Test;
        $test->tenant_id = $tenant->id;
        $test->test_title = 'آزمون بررسی';
        $test->lesson = 'شیمی';
        $test->exam_type = 'quiz';
        $test->total_marks = 20;
        $test->created_by_user_id = $consultant->id;
        $test->save();

        foreach ($questions as $i => $question) {
            $test->questions()->attach($question->id, [
                'tenant_id' => $tenant->id,
                'position' => $i + 1,
            ]);
        }

        return StudentAssignedQuiz::create([
            'tenant_id' => $tenant->id,
            'test_id' => $test->id,
            'student_id' => $student->id,
            'assigned_by_user_id' => $consultant->id,
            'assigned_at' => now(),
            'scheduled_at' => now()->subMinutes(10),
            'ends_at' => now()->addHours(2),
            'status' => 'scheduled',
        ]);
    }

    public function test_the_exam_card_count_comes_from_the_pivot_not_the_stale_column(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $questions = [
            $this->question($tenant, 'سوال ۱'),
            $this->question($tenant, 'سوال ۲'),
            $this->question($tenant, 'سوال ۳'),
            $this->question($tenant, 'سوال ۴'),
            $this->question($tenant, 'سوال ۵'),
        ];
        $assignment = $this->exam($tenant, $consultant, $student, $questions);

        // The bug being fixed: tests.question_count stays stale (it was never
        // set here), so a card reading it would show nothing at all.
        $this->assertNull($assignment->test->question_count);

        $this->actingAs($consultant)
            ->get("http://review.test/consultant/students/{$student->id}/exams")
            ->assertOk()
            ->assertSee('۵ سوال');
    }

    public function test_the_review_page_shows_the_answers_but_cannot_create_an_attempt(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $assignment = $this->exam($tenant, $consultant, $student, [
            $this->question($tenant, 'کدام گزینه درست است؟'),
        ]);

        $response = $this->actingAs($consultant)
            ->get("http://review.test/consultant/students/{$student->id}/exams/{$assignment->id}/run");

        $response->assertOk()
            ->assertSee('کدام گزینه درست است؟')
            ->assertSee('گزینهٔ صحیح')
            ->assertSee('opt--correct', false);

        // Read-only: no runner, no radios, no timer, no submit surface.
        $response->assertDontSee('id="exam-runner"', false);
        $response->assertDontSee('type="radio"', false);
        $response->assertDontSee('id="attempt-form"', false);
        $response->assertDontSee('id="finish-exam"', false);
        $response->assertDontSee('name="answers[', false);

        $this->assertSame(0, StudentTestAttempt::count());
    }

    public function test_the_workspace_no_longer_offers_a_consultant_attempt_route(): void
    {
        $this->assertFalse(
            app('router')->getRoutes()->hasNamedRoute('consultant.student.exams.attempt')
        );
    }

    public function test_the_consultant_result_page_reads_the_same_contract_as_the_student(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $assignment = $this->exam($tenant, $consultant, $student, [
            $this->question($tenant, 'کدام گزینه درست است؟'),
        ]);

        $attempt = StudentTestAttempt::create([
            'tenant_id' => $tenant->id,
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'test_id' => $assignment->test_id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'score_raw' => 20,
            'score_simple_percent' => 100,
            'score_negative_percent' => 100,
            'time_taken_seconds' => 600,
        ]);

        // A mixed lesson list so the per-lesson breakdown renders.
        $other = $this->question($tenant, 'سوال فیزیک', 'فیزیک');
        $assignment->test->questions()->attach($other->id, ['tenant_id' => $tenant->id, 'position' => 2]);
        $attempt->update(['score_simple_percent' => 50]);

        AttemptAnswer::create([
            'tenant_id' => $tenant->id,
            'attempt_id' => $attempt->id,
            'student_id' => $student->id,
            'question_id' => $assignment->test->questions()->first()->id,
            'is_correct' => true,
            'is_graded' => true,
        ]);

        $response = $this->actingAs($consultant)
            ->get("http://review.test/consultant/students/{$student->id}/exams/{$assignment->id}/result");

        $response->assertOk()
            ->assertSee('۵۰')
            ->assertSee('صحیح')
            ->assertSee('به تفکیک درس');

        // The student sees the identical numbers through the shared partials.
        $this->actingAs($student, 'student')
            ->get("http://review.test/student/exams/{$assignment->id}/result")
            ->assertOk()
            ->assertSee('۵۰')
            ->assertSee('به تفکیک درس');
    }

    public function test_an_ungraded_question_is_never_shown_as_right_or_wrong(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();

        // No is_correct answer at all: the bank could not grade this one.
        $ungraded = Question::create([
            'tenant_id' => $tenant->id,
            'question_text' => 'سوال بدون پاسخ صحیح',
            'question_type' => 'multiple_choice',
            'subject' => 'شیمی',
        ]);
        $ungradedOption = Answer::create([
            'tenant_id' => $tenant->id,
            'question_id' => $ungraded->id,
            'answer_text' => 'گزینهٔ بی‌کلید',
            'is_correct' => false,
        ]);

        $assignment = $this->exam($tenant, $consultant, $student, [$ungraded]);

        $attempt = StudentTestAttempt::create([
            'tenant_id' => $tenant->id,
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'test_id' => $assignment->test_id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'score_raw' => 0,
            'score_simple_percent' => 0,
            'score_negative_percent' => 0,
            'time_taken_seconds' => 60,
        ]);

        AttemptAnswer::create([
            'tenant_id' => $tenant->id,
            'attempt_id' => $attempt->id,
            'student_id' => $student->id,
            'question_id' => $ungraded->id,
            'chosen_answer_id' => $ungradedOption->id,
            'is_correct' => false,
            'is_graded' => false,
        ]);

        $response = $this->actingAs($student, 'student')
            ->get("http://review.test/student/exams/{$assignment->id}/result");

        $response->assertOk()
            ->assertSee('بدون امتیاز')
            ->assertSee('is-ungraded', false)
            // The student's pick is shown, but never highlighted as correct.
            ->assertSee('opt--chosen', false)
            ->assertDontSee('opt--correct', false)
            ->assertDontSee('opt--chosen-wrong', false);
    }
}
