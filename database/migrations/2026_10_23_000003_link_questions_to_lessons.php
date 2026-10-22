<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A question must be able to belong to the lesson it was written for.
 *
 * The first cut linked a lesson to its questions through
 * `lesson_ai_drafts.released_question_id` — ONE nullable id. That silently
 * capped a lesson at a single question (the demo's page shows three, and
 * `LessonQuestionController::releaseAll()` broke out of its loop after the
 * first), and it made the lesson→question link a property of a *draft* rather
 * than of the lesson.
 *
 * `questions.lesson_id` says the obvious thing instead: these questions are
 * this lesson's. The row still carries `tenant_id = NULL`, so releasing into
 * the shared bank (used by the exam builder) keeps working exactly as before —
 * this only ADDS the link the reader needs.
 *
 * `nullOnDelete`, never cascade: deleting a lesson must unlink its questions,
 * not delete rows the shared bank and past exams already reference.
 *
 * `released_count` replaces the "one release per draft, ever" guard with
 * "one release per question index", so a 3-question draft releases three.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('questions', 'lesson_id')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->foreignId('lesson_id')->nullable()->after('tenant_id')
                    ->constrained('book_lessons')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('lesson_ai_drafts', 'released_count')) {
            Schema::table('lesson_ai_drafts', function (Blueprint $table) {
                $table->unsignedInteger('released_count')->default(0)->after('released_question_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('questions', 'lesson_id')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('lesson_id');
            });
        }

        if (Schema::hasColumn('lesson_ai_drafts', 'released_count')) {
            Schema::table('lesson_ai_drafts', function (Blueprint $table) {
                $table->dropColumn('released_count');
            });
        }
    }
};
