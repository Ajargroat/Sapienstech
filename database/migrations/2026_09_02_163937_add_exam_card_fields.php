<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $t) {
            if (! Schema::hasColumn('tests', 'description')) {
                $t->text('description')->nullable()->after('lesson');
            }
            if (! Schema::hasColumn('tests', 'question_count')) {
                $t->unsignedSmallInteger('question_count')->nullable()->after('total_marks');
            }
        });

        // quiz/comprehensive drive the card badges; the legacy values stay
        // valid so previously imported tests keep rendering. MODIFY is
        // idempotent, so re-running this migration is safe.
        DB::statement(
            "ALTER TABLE tests MODIFY exam_type "
            . "ENUM('quiz','comprehensive','progress','mock','online_quiz','single_lesson') "
            . "NOT NULL DEFAULT 'quiz'"
        );

        // These two tables were created without timestamps, which makes
        // Eloquent create()/save() fail. The exam workspace writes to both.
        // Declared explicitly because Blueprint::timestamps() returns a
        // Collection in Laravel 12 and cannot be chained.
        foreach (['student_assigned_quizzes', 'student_test_attempts'] as $table) {
            if (! Schema::hasColumn($table, 'created_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->timestamp('created_at')->nullable();
                    $t->timestamp('updated_at')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $t) {
            $t->dropColumn(['description', 'question_count']);
        });

        DB::statement(
            "ALTER TABLE tests MODIFY exam_type "
            . "ENUM('progress','mock','online_quiz','single_lesson') NOT NULL DEFAULT 'progress'"
        );

        foreach (['student_assigned_quizzes', 'student_test_attempts'] as $table) {
            if (Schema::hasColumn($table, 'created_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn(['created_at', 'updated_at']);
                });
            }
        }
    }
};
