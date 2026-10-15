<?php

namespace Tests\Feature;

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
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The student sits the exam themselves: the dashboard card opens the runner
 * inside the window, the server owns the clock, grading is automatic and the
 * result is a percentage (TASK AC2/AC4/AC6).
 *
 * Rows are written directly (as StudentReportCardTest does) so the tests pin
 * behaviour rather than creation UI.
 */
class StudentExamRunnerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Tenant, User, Student} */
    private function tenantContext(string $host = 'runner.test'): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        $consultant = User::factory()->for($tenant)->consultant()->create();
        $student = Student::factory()->for($tenant)->create();

        return [$tenant, $consultant, $student];
    }

    /** A question carrying one correct answer plus $wrongCount distractors. */
    private function question(Tenant $tenant, string $text, int $wrongCount = 3, ?string $subject = 'شیمی'): Question
    {
        $question = Question::create([
            'tenant_id' => $tenant->id,
            'question_text' => $text,
            'question_type' => 'multiple_choice',
            'difficulty' => 'Easy',
            'subject' => $subject,
            'is_active' => true,
        ]);

        Answer::create([
            'tenant_id' => $tenant->id,
            'question_id' => $question->id,
            'answer_text' => 'گزینهٔ صحیح',
            'is_correct' => true,
        ]);

        for ($i = 0; $i < $wrongCount; $i++) {
            Answer::create([
                'tenant_id' => $tenant->id,
                'question_id' => $question->id,
                'answer_text' => 'گزینهٔ نادرست '.($i + 1),
                'is_correct' => false,
            ]);
        }

        return $question;
    }

    /** An exam-assigned question the bank has no correct answer for. */
    private function ungradedQuestion(Tenant $tenant, string $text): Question
    {
        $question = Question::create([
            'tenant_id' => $tenant->id,
            'question_text' => $text,
            'question_type' => 'multiple_choice',
            'difficulty' => 'Easy',
            'subject' => 'شیمی',
            'is_active' => true,
        ]);

        foreach (['الف', 'ب', 'ج', 'د'] as $label) {
            Answer::create([
                'tenant_id' => $tenant->id,
                'question_id' => $question->id,
                'answer_text' => $label,
                'is_correct' => false,
            ]);
        }

        return $question;
    }

    /**
     * @param  array<int, Question>  $questions
     */
    private function assignExam(
        Tenant $tenant,
        User $consultant,
        Student $student,
        array $questions,
        array $overrides = []
    ): StudentAssignedQuiz {
        $test = new Test;
        $test->tenant_id = $tenant->id;
        $test->test_title = $overrides['title'] ?? 'آزمون شیمی — فصل اول';
        $test->lesson = 'شیمی';
        $test->exam_type = 'quiz';
        $test->question_count = count($questions);
        $test->total_marks = $overrides['total_marks'] ?? 20;
        $test->time_limit_minutes = $overrides['time_limit_minutes'] ?? null;
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
            'scheduled_at' => $overrides['scheduled_at'] ?? now()->subMinutes(5),
            'ends_at' => $overrides['ends_at'] ?? now()->addHours(2),
            'status' => 'scheduled',
            'is_completed' => false,
        ]);
    }

    /** The ids of a question's options, correct one first. */
    private function optionIds(Question $question): array
    {
        return $question->answers()->orderByDesc('is_correct')->orderBy('id')->pluck('id')->all();
    }

    public function test_the_runner_opens_inside_the_window_and_starts_the_clock(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'کدام گزینه درست است؟');
        $assignment = $this->assignExam($tenant, $consultant, $student, [$q], ['time_limit_minutes' => 30]);

        $response = $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$assignment->id}");

        $response->assertOk()
            ->assertSee('کدام گزینه درست است؟')
            ->assertSee('id="exam-runner"', false)
            // The countdown is seeded from the server, not the browser clock.
            ->assertSee('data-remaining="', false)
            ->assertDontSee('id="time-taken"', false)
            // MathJax 3 config + loader ride on the student layout head, so the
            // runner (a real question surface) typesets $…$ on load.
            ->assertSee('cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js', false)
            ->assertSee('window.MathJax', false);

        $attempt = $assignment->attempts()->first();
        $this->assertNotNull($attempt);
        $this->assertSame('in_progress', $attempt->status);
        $this->assertSame('in_progress', $assignment->fresh()->status);

        // The clock is seeded to the full limit for a fresh attempt.
        $this->assertSame(30 * 60, (int) round($assignment->fresh()->windowOpen() ? 1800 : 0));
    }

    public function test_the_app_clock_is_iran_wall_clock_time(): void
    {
        // Exam windows are wall clocks the consultant typed in the dashboard
        // (Iran local time). With the clock on UTC, `now()` ran 3.5 hours
        // behind, so a 22:29 exam only opened around 02:00. Pinning the app to
        // Asia/Tehran is what makes windowOpen() flip at the right minute.
        $this->assertSame('Asia/Tehran', config('app.timezone'));
        $this->assertSame('Asia/Tehran', date_default_timezone_get());
    }

    public function test_a_wall_clock_window_opens_at_its_own_local_minute(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'سوال');

        // A window that opens 30 minutes from NOW, expressed as a wall clock.
        // If the app clock were UTC this same stored string would be judged
        // 'in the future' for another 3.5 hours and never open on time.
        $start = now()->addMinutes(30);
        $assignment = $this->assignExam($tenant, $consultant, $student, [$q], [
            'scheduled_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
        ]);

        $this->assertFalse($assignment->fresh()->windowOpen());

        $this->travel(31)->minutes();
        $this->assertTrue($assignment->fresh()->windowOpen());
        $this->assertSame('in_progress', $assignment->fresh()->effectiveStatus());
    }

    public function test_the_start_button_carries_a_real_instant_not_a_fake_utc_suffix(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'سوال');

        $this->assignExam($tenant, $consultant, $student, [$q], [
            'scheduled_at' => now()->addDays(2),
            'ends_at' => now()->addDays(3),
        ]);

        $html = $this->actingAs($student, 'student')
            ->get('http://runner.test/student/dashboard')
            ->assertOk()
            ->getContent();

        // The browser compares this against the real clock, so it must be a
        // true instant with an offset — never a wall-clock string + 'Z', which
        // the JS would read 3.5 hours early.
        $this->assertMatchesRegularExpression('/data-start-at="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}"/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-start-at="[^"]+Z"/', $html);
    }

    public function test_a_closed_window_is_not_sit(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'سوال');

        // The window ended an hour ago.
        $closed = $this->assignExam($tenant, $consultant, $student, [$q], [
            'scheduled_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
        ]);
        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$closed->id}")
            ->assertNotFound();

        // It has not started yet either.
        $future = $this->assignExam($tenant, $consultant, $student, [$q], [
            'scheduled_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
        ]);
        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$future->id}")
            ->assertNotFound();

        $this->assertSame(0, StudentTestAttempt::count());
    }

    public function test_an_open_ended_window_stays_open_until_it_is_sat(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'سوال');
        $assignment = $this->assignExam($tenant, $consultant, $student, [$q], [
            'scheduled_at' => now()->subDays(3),
            'ends_at' => null, // bulk/legacy row: no end
        ]);

        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$assignment->id}")
            ->assertOk();

        $this->assertSame('in_progress', $assignment->fresh()->status);
    }

    public function test_grading_is_a_percentage_over_the_graded_questions_only(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();

        $a = $this->question($tenant, 'سوال یک');
        $b = $this->question($tenant, 'سوال دو');
        $c = $this->question($tenant, 'سوال سه');
        $ungraded = $this->ungradedQuestion($tenant, 'سوال بدون پاسخ صحیح');

        $assignment = $this->assignExam($tenant, $consultant, $student, [$a, $b, $c, $ungraded]);

        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$assignment->id}")
            ->assertOk();

        $optA = $this->optionIds($a);
        $optB = $this->optionIds($b);
        $optUngraded = $this->optionIds($ungraded);

        $this->actingAs($student, 'student')
            ->post("http://runner.test/student/exams/{$assignment->id}/attempt", [
                'answers' => [
                    $a->id => $optA[0],       // correct
                    $b->id => $optB[1],       // wrong
                    $ungraded->id => $optUngraded[0],
                    // $c left unanswered
                ],
            ])
            ->assertRedirect(route('student.exams.result', $assignment));

        $attempt = $assignment->attempts()->where('status', 'completed')->first();
        $this->assertNotNull($attempt);

        // Denominator is the 3 graded questions, not 4: 1/3 = 33.33%, and the
        // raw score is one share of the 20 marks on that same denominator — so
        // raw / total_marks agrees with the percentage the hero prints.
        $this->assertEqualsWithDelta(33.33, (float) $attempt->score_simple_percent, 0.01);
        $this->assertEqualsWithDelta(6.67, (float) $attempt->score_raw, 0.01);

        $rows = AttemptAnswer::where('attempt_id', $attempt->id)->get()->keyBy('question_id');
        $this->assertSame(4, $rows->count());
        $this->assertTrue((bool) $rows[$a->id]->is_correct);
        $this->assertNull($rows[$c->id]->chosen_answer_id);
        $this->assertTrue((bool) $rows[$c->id]->is_graded);

        // The ungraded row snapshots its flag and is never counted correct,
        // even though the student picked an option.
        $this->assertFalse((bool) $rows[$ungraded->id]->is_graded);
        $this->assertFalse((bool) $rows[$ungraded->id]->is_correct);
        $this->assertSame($optUngraded[0], $rows[$ungraded->id]->chosen_answer_id);

        $this->assertSame('completed', $assignment->fresh()->status);
        $this->assertTrue((bool) $assignment->fresh()->is_completed);
    }

    public function test_an_answer_from_another_question_is_never_accepted(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'سوال');
        $other = $this->question($tenant, 'سوال دیگر');
        $assignment = $this->assignExam($tenant, $consultant, $student, [$q]);

        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$assignment->id}");

        // The correct option exists, but it belongs to another question.
        $foreign = $this->optionIds($other)[0];

        $this->actingAs($student, 'student')
            ->post("http://runner.test/student/exams/{$assignment->id}/attempt", [
                'answers' => [$q->id => $foreign],
            ]);

        $attempt = $assignment->attempts()->where('status', 'completed')->first();
        $row = AttemptAnswer::where('attempt_id', $attempt->id)->where('question_id', $q->id)->first();

        $this->assertNull($row->chosen_answer_id);
        $this->assertFalse((bool) $row->is_correct);
    }

    public function test_the_window_closes_the_student_out_and_resumes_the_same_attempt(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'سوال');
        $assignment = $this->assignExam($tenant, $consultant, $student, [$q], ['time_limit_minutes' => 30]);

        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$assignment->id}")
            ->assertOk();

        $first = $assignment->attempts()->first();

        // Reopening resumes it rather than starting a second clock.
        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$assignment->id}")
            ->assertOk();

        $this->assertSame(1, $assignment->attempts()->count());
        $this->assertSame($first->id, $assignment->attempts()->first()->id);
    }

    public function test_a_finished_attempt_redirects_to_the_result(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'سوال');
        $assignment = $this->assignExam($tenant, $consultant, $student, [$q]);

        StudentTestAttempt::create([
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
        ]);

        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$assignment->id}")
            ->assertRedirect(route('student.exams.result', $assignment));
    }

    public function test_elapsed_time_is_capped_at_the_limit_by_the_server(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'سوال');
        $assignment = $this->assignExam($tenant, $consultant, $student, [$q], ['time_limit_minutes' => 30]);

        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$assignment->id}");

        // The student wandered off for two hours.
        $attempt = $assignment->attempts()->first();
        $attempt->update(['started_at' => Carbon::now()->subHours(2)]);

        $this->actingAs($student, 'student')
            ->post("http://runner.test/student/exams/{$assignment->id}/attempt", [
                'answers' => [$q->id => $this->optionIds($q)[0]],
            ]);

        $this->assertSame(30 * 60, (int) $attempt->fresh()->time_taken_seconds);
    }

    public function test_another_students_assignment_is_not_reachable(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $other = Student::factory()->for($tenant)->create();
        $q = $this->question($tenant, 'سوال');
        $assignment = $this->assignExam($tenant, $consultant, $other, [$q]);

        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$assignment->id}")
            ->assertNotFound();
    }

    public function test_the_result_reads_back_the_attempt_it_just_graded(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        // Two different lessons so the per-lesson section of the hero renders.
        $a = $this->question($tenant, 'سوال یک', subject: 'شیمی');
        $b = $this->question($tenant, 'سوال دو', subject: 'فیزیک');
        $assignment = $this->assignExam($tenant, $consultant, $student, [$a, $b]);

        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$assignment->id}");

        $this->actingAs($student, 'student')
            ->post("http://runner.test/student/exams/{$assignment->id}/attempt", [
                'answers' => [
                    $a->id => $this->optionIds($a)[0],
                    $b->id => $this->optionIds($b)[0],
                ],
            ]);

        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$assignment->id}/result")
            ->assertOk()
            ->assertSee('۱۰۰')          // percent, not 20/20
            ->assertSee('صحیح')
            ->assertSee('به تفکیک درس');
    }

    /** The dashboard card follows the window, never the stored status (R5). */
    public function test_starting_an_exam_keeps_its_card_on_the_dashboard(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'سوال');
        $assignment = $this->assignExam($tenant, $consultant, $student, [$q], [
            'title' => 'آزمون در حال برگزاری',
            'scheduled_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($student, 'student')
            ->get("http://runner.test/student/exams/{$assignment->id}")
            ->assertOk();

        // Opening the runner stores the history marker…
        $this->assertSame('in_progress', $assignment->fresh()->status);

        // …which must not take the card away.
        $this->actingAs($student, 'student')
            ->get('http://runner.test/student/dashboard')
            ->assertOk()
            ->assertSee('آزمون در حال برگزاری');
    }

    public function test_an_exam_without_an_end_stays_listed_after_its_start_day(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'سوال');
        $assignment = $this->assignExam($tenant, $consultant, $student, [$q], [
            'title' => 'آزمون بی‌پایان',
            'scheduled_at' => now()->subDays(3),
            'ends_at' => null, // bulk/legacy row: NULL end never closes the window
        ]);

        $this->actingAs($student, 'student')
            ->get('http://runner.test/student/dashboard')
            ->assertOk()
            ->assertSee('آزمون بی‌پایان');
    }

    public function test_a_finished_exam_leaves_the_upcoming_cards(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'سوال');
        $assignment = $this->assignExam($tenant, $consultant, $student, [$q], [
            'title' => 'آزمون تمام‌شده',
        ]);
        $assignment->update(['status' => 'completed', 'is_completed' => true]);

        // No attempt was made, so the recent-results section cannot show it
        // either — a completed row belongs nowhere on the cards grid.
        $this->actingAs($student, 'student')
            ->get('http://runner.test/student/dashboard')
            ->assertOk()
            ->assertDontSee('آزمون تمام‌شده');
    }

    public function test_the_start_button_is_rendered_and_only_hidden_before_the_start(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();
        $q = $this->question($tenant, 'سوال');

        $future = $this->assignExam($tenant, $consultant, $student, [$q], [
            'title' => 'آزمون آینده',
            'scheduled_at' => now()->addDays(2),
            'ends_at' => now()->addDays(3),
        ]);

        $hidden = $this->actingAs($student, 'student')
            ->get('http://runner.test/student/dashboard')
            ->assertOk()
            ->getContent();
        // Always rendered (student-dashboard.js flips it live), hidden until then.
        $this->assertMatchesRegularExpression('/data-start-at="[^"]+"\s+hidden\s*>/s', $hidden);

        $future->update(['scheduled_at' => now()->subMinutes(5)]);

        $live = $this->actingAs($student, 'student')
            ->get('http://runner.test/student/dashboard')
            ->getContent();
        $this->assertMatchesRegularExpression('/data-start-at="[^"]+"\s*>/s', $live);
        $this->assertDoesNotMatchRegularExpression('/data-start-at="[^"]+"\s+hidden\s*>/s', $live);
    }
}
