<?php

namespace App\Support\Lessons;

use App\Models\BookLesson;
use App\Models\LessonAiDraft;
use App\Models\Question;
use App\Support\QuestionBankMeta;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Writing ONE approved AI question into the SHARED question bank — and only
 * when the tenant has switched that on.
 *
 * Why one transaction for the question and its four answers: `ExamGrader::isGraded()`
 * treats a question as gradeable only when an `answers` row with
 * `is_correct = true` exists. A question row written first and answers second
 * (or not at all) is silently excluded from the denominator — a wrong answer
 * that nobody is told about. So both tables are written together or not at all.
 *
 * Why `tenant_id = NULL`: the bank is a deliberate shared pool
 * (`SharedAcrossTenants`), and the user's decision is that a released AI
 * question joins it. A tenant-owned row would be invisible to every other
 * academy and would silently re-create one bank per tenant — the thing
 * PREFERENCE §5 rejected.
 *
 * Why `lesson_id` is ALSO written: the bank is where exams read from, and the
 * lesson is where a student meets the question. Those are two different
 * readers of one row. The first cut tried to serve both from
 * `lesson_ai_drafts.released_question_id`, a single nullable id, which capped
 * a lesson at one question and made the link a property of a draft. The column
 * on the question itself is what lets the reader show the whole set.
 */
class LessonQuestionRelease
{
    /**
     * @return int the new questions.id
     *
     * @throws RuntimeException when the draft is not in a releasable state
     */
    public function release(LessonAiDraft $draft, BookLesson $lesson, int $questionIndex): int
    {
        $questions = $draft->questions();

        if ($draft->kind !== LessonAiDraft::KIND_QUESTIONS
            || ! in_array($draft->status, ['approved', 'released'], true)) {
            throw new RuntimeException('A question can only be released from an approved draft.');
        }

        // Per INDEX, not per draft: a three-question draft releases three
        // times, and each index releases exactly once.
        if ($questionIndex < (int) $draft->released_count) {
            throw new RuntimeException('This question was already released.');
        }

        $question = $questions[$questionIndex] ?? null;

        if ($question === null) {
            throw new RuntimeException('Unknown question index.');
        }

        return DB::transaction(function () use ($question, $lesson, $draft, $questionIndex) {
            $stored = Question::create([
                'tenant_id' => null,
                'lesson_id' => $lesson->id,
                'question_text' => (string) $question['stem'],
                'question_type' => 'multiple_choice',
                'difficulty' => (string) $question['difficulty'],
                'solution_text' => (string) $question['explanation'],
                'subject' => QuestionBankMeta::subject($lesson->subject) ?? $lesson->subject,
                'corp' => QuestionBankMeta::corp($lesson->book_title),
                'chapter_label' => QuestionBankMeta::chapterLabel($lesson->chapter_title, $lesson->grade),
                'question_number_in_book' => $lesson->source_page_from !== null ? (string) $lesson->source_page_from : null,
            ]);

            foreach ((array) $question['options'] as $option) {
                $stored->answers()->create([
                    'tenant_id' => null,
                    'answer_text' => (string) $option['text'],
                    'is_correct' => (bool) $option['is_correct'],
                ]);
            }

            $released = $questionIndex + 1;

            $draft->forceFill([
                // The first released question id is kept as the audit anchor
                // (what this draft produced); `released_count` is the counter
                // the guards read. `released` only once every index is out.
                'released_question_id' => $draft->released_question_id ?? $stored->id,
                'released_count' => $released,
                'status' => $released >= count($draft->questions()) ? 'released' : 'approved',
            ])->save();

            return (int) $stored->id;
        });
    }
}
