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
use App\Support\ExamReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The report-card data contract (TASK AC11).
 *
 * The report card is not built yet, so this pins the shape it will consume:
 * every count is derived from the attempt's own rows, ungraded questions are
 * their own bucket rather than a kind of wrong answer, and the percentage
 * ignores them in the denominator.
 */
class ExamReportContractTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Tenant, User, Student} */
    private function tenantContext(): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => 'report.test', 'is_primary' => true]);
        $consultant = User::factory()->for($tenant)->consultant()->create();
        $student = Student::factory()->for($tenant)->create();

        return [$tenant, $consultant, $student];
    }

    private function question(Tenant $tenant, string $subject): Question
    {
        return Question::create([
            'tenant_id' => $tenant->id,
            'question_text' => "سوال {$subject}",
            'question_type' => 'multiple_choice',
            'difficulty' => 'Easy',
            'subject' => $subject,
            'is_active' => true,
        ]);
    }

    /** The question's single option — a real row, so chosen_answer_id can point at it. */
    private function option(Tenant $tenant, Question $question): Answer
    {
        return Answer::create([
            'tenant_id' => $tenant->id,
            'question_id' => $question->id,
            'answer_text' => 'گزینه',
            'is_correct' => false,
        ]);
    }

    /**
     * The four-state truth table the review renders.
     *
     * These are attribute bags, not rows: classify() reads the row's own
     * columns and must not need a database round trip.
     */
    public function test_the_truth_table_never_folds_an_ungraded_row_into_wrong(): void
    {
        $this->assertSame('ungraded', ExamReport::classify(null));

        // Not graded, whatever else the row says.
        $this->assertSame('ungraded', ExamReport::classify(new AttemptAnswer([
            'is_graded' => false,
            'is_correct' => true,
            'chosen_answer_id' => 7,
        ])));

        $this->assertSame('correct', ExamReport::classify(new AttemptAnswer([
            'is_graded' => true,
            'is_correct' => true,
            'chosen_answer_id' => 7,
        ])));

        $this->assertSame('wrong', ExamReport::classify(new AttemptAnswer([
            'is_graded' => true,
            'is_correct' => false,
            'chosen_answer_id' => 7,
        ])));

        $this->assertSame('unanswered', ExamReport::classify(new AttemptAnswer([
            'is_graded' => true,
            'is_correct' => false,
            'chosen_answer_id' => null,
        ])));
    }

    public function test_the_contract_carries_every_count_the_report_card_needs(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();

        $chemistry = [$this->question($tenant, 'شیمی'), $this->question($tenant, 'شیمی')];
        $physics = [$this->question($tenant, 'فیزیک')];

        $test = new Test;
        $test->tenant_id = $tenant->id;
        $test->test_title = 'آزمون قرارداد';
        $test->lesson = 'شیمی';
        $test->exam_type = 'quiz';
        $test->total_marks = 12;
        $test->created_by_user_id = $consultant->id;
        $test->save();

        foreach ([...$chemistry, ...$physics] as $i => $question) {
            $test->questions()->attach($question->id, ['tenant_id' => $tenant->id, 'position' => $i + 1]);
        }

        $assignment = StudentAssignedQuiz::create([
            'tenant_id' => $tenant->id,
            'test_id' => $test->id,
            'student_id' => $student->id,
            'assigned_by_user_id' => $consultant->id,
            'assigned_at' => now(),
            'scheduled_at' => now()->subMinutes(5),
            'ends_at' => now()->addHour(),
            'status' => 'completed',
            'is_completed' => true,
        ]);

        $attempt = StudentTestAttempt::create([
            'tenant_id' => $tenant->id,
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(20),
            'completed_at' => now()->subMinutes(5),
            'score_raw' => 4,
            'score_simple_percent' => 33.33,
            'score_negative_percent' => 25,
            'time_taken_seconds' => 900,
        ]);

        // شیمی: one right, one left blank. فیزیک: one ungraded row.
        $rows = [
            ['question_id' => $chemistry[0]->id, 'is_correct' => true, 'is_graded' => true, 'chosen_answer_id' => $this->option($tenant, $chemistry[0])->id],
            ['question_id' => $chemistry[1]->id, 'is_correct' => false, 'is_graded' => true, 'chosen_answer_id' => null],
            ['question_id' => $physics[0]->id, 'is_correct' => false, 'is_graded' => false, 'chosen_answer_id' => $this->option($tenant, $physics[0])->id],
        ];

        foreach ($rows as $row) {
            AttemptAnswer::create([
                'tenant_id' => $tenant->id,
                'attempt_id' => $attempt->id,
                'student_id' => $student->id,
                ...$row,
            ]);
        }

        $report = ExamReport::fromAttempt($attempt);

        $this->assertSame($attempt->id, $report['attempt_id']);
        $this->assertSame($test->id, $report['test_id']);
        $this->assertSame($student->id, $report['student_id']);

        // The percentage is the stored one, and the negative variant rides along.
        $this->assertSame(33.33, $report['percent']);
        $this->assertSame(25.0, $report['negative_percent']);

        $this->assertSame(1, $report['correct']);
        $this->assertSame(0, $report['wrong']);
        $this->assertSame(1, $report['unanswered']);
        $this->assertSame(1, $report['ungraded']);
        $this->assertSame(3, $report['total']);
        // Only the two graded questions: the ungraded one is not a denominator.
        $this->assertSame(2, $report['graded']);

        $this->assertSame(900, $report['time_taken_seconds']);
        $this->assertNotNull($report['completed_at']);
        $this->assertSame(now()->subMinutes(5)->format('Y-m-d'), $report['date']);

        // Lessons keep the exam's own order and carry their own breakdown.
        $this->assertSame(['شیمی', 'فیزیک'], array_keys($report['per_lesson']));

        $this->assertSame(
            ['correct' => 1, 'wrong' => 0, 'unanswered' => 1, 'ungraded' => 0, 'total' => 2, 'percent' => 50.0, 'graded' => 2],
            $report['per_lesson']['شیمی']
        );
        // A lesson with nothing gradeable has no percentage — not zero percent.
        $this->assertSame(
            ['correct' => 0, 'wrong' => 0, 'unanswered' => 0, 'ungraded' => 1, 'total' => 1, 'percent' => null, 'graded' => 0],
            $report['per_lesson']['فیزیک']
        );
    }

    public function test_a_question_with_no_attempt_row_counts_as_ungraded_not_unanswered(): void
    {
        [$tenant, $consultant, $student] = $this->tenantContext();

        $question = $this->question($tenant, 'شیمی');
        $test = new Test;
        $test->tenant_id = $tenant->id;
        $test->test_title = 'آزمون بی‌ردیف';
        $test->lesson = 'شیمی';
        $test->exam_type = 'quiz';
        $test->total_marks = 5;
        $test->created_by_user_id = $consultant->id;
        $test->save();
        $test->questions()->attach($question->id, ['tenant_id' => $tenant->id, 'position' => 1]);

        $assignment = StudentAssignedQuiz::create([
            'tenant_id' => $tenant->id,
            'test_id' => $test->id,
            'student_id' => $student->id,
            'assigned_by_user_id' => $consultant->id,
            'assigned_at' => now(),
            'status' => 'completed',
            'is_completed' => true,
        ]);

        // Legacy attempt: stored before attempt_answers existed for it.
        $attempt = StudentTestAttempt::create([
            'tenant_id' => $tenant->id,
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(20),
            'completed_at' => now(),
            'score_raw' => 0,
            'score_simple_percent' => 0,
            'score_negative_percent' => 0,
            'time_taken_seconds' => 60,
        ]);

        $report = ExamReport::fromAttempt($attempt);

        $this->assertSame(1, $report['total']);
        $this->assertSame(1, $report['ungraded']);
        $this->assertSame(0, $report['unanswered']);
        $this->assertSame(0, $report['graded']);
        $this->assertNull($report['per_lesson']['شیمی']['percent']);
    }

    public function test_lessons_of_keeps_the_exams_own_order_without_repeats(): void
    {
        [$tenant] = $this->tenantContext();

        $questions = collect([
            $this->question($tenant, 'فیزیک'),
            $this->question($tenant, 'شیمی'),
            $this->question($tenant, 'فیزیک'),
        ]);

        $this->assertSame(['فیزیک', 'شیمی'], ExamReport::lessonsOf($questions));
    }
}
