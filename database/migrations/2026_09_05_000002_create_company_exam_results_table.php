<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row = one mock exam a student took from a corporation. This is the
 * external half of the کارنامه workspace; the internal half (consultant-made
 * exams) is derived live from student_assigned_quizzes + tests +
 * student_test_attempts, so those are never duplicated here.
 *
 * `exam_rank` rather than `rank`: RANK is reserved in MySQL 8 and raw queries
 * would have to quote it forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('exam_companies')->cascadeOnDelete();

            $table->string('title');
            $table->date('exam_date');
            $table->enum('status', ['completed', 'absent', 'pending'])->default('completed');

            $table->unsignedSmallInteger('total_questions')->nullable();
            $table->unsignedSmallInteger('correct_count')->nullable();
            $table->unsignedSmallInteger('wrong_count')->nullable();
            $table->unsignedSmallInteger('blank_count')->nullable();

            $table->decimal('percent', 5, 2)->nullable();
            $table->unsignedInteger('exam_rank')->nullable();
            $table->unsignedInteger('participants')->nullable();

            // {"زیست": 78, "شیمی": 66, ...} — per-lesson percentages.
            $table->json('lesson_percents')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'exam_date']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_exam_results');
    }
};
