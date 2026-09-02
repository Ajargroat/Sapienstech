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
            if (! Schema::hasColumn('tests', 'lesson')) {
                $t->string('lesson', 100)->nullable()->after('test_title');
            }
            if (! Schema::hasColumn('tests', 'exam_type')) {
                $t->enum('exam_type', ['progress', 'mock', 'online_quiz', 'single_lesson'])
                    ->default('progress')->after('lesson');
            }
            if (! Schema::hasColumn('tests', 'total_marks')) {
                $t->decimal('total_marks', 6, 2)->default(20)->after('time_limit_minutes');
            }
        });

        Schema::table('student_assigned_quizzes', function (Blueprint $t) {
            if (! Schema::hasColumn('student_assigned_quizzes', 'scheduled_at')) {
                $t->dateTime('scheduled_at')->nullable()->after('assigned_at');
            }
            if (! Schema::hasColumn('student_assigned_quizzes', 'status')) {
                $t->enum('status', ['scheduled', 'in_progress', 'grading', 'completed', 'missed'])
                    ->default('scheduled')->after('scheduled_at');
            }
        });

        Schema::table('student_test_attempts', function (Blueprint $t) {
            if (! Schema::hasColumn('student_test_attempts', 'status')) {
                $t->enum('status', ['in_progress', 'completed', 'abandoned', 'expired'])
                    ->default('in_progress')->after('test_id');
            }
            if (! Schema::hasColumn('student_test_attempts', 'started_at')) {
                $t->timestamp('started_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('student_test_attempts', 'score_raw')) {
                $t->decimal('score_raw', 6, 2)->nullable()->after('started_at');
            }
        });

        Schema::table('test_questions', function (Blueprint $t) {
            if (! Schema::hasColumn('test_questions', 'points')) {
                $t->decimal('points', 5, 2)->nullable()->after('position');
            }
        });

        // MODIFY is a no-op when already applied, so this is safe on your live DB.
        DB::statement('ALTER TABLE student_test_attempts MODIFY test_id bigint UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE student_test_attempts MODIFY score_simple_percent decimal(5,2) NULL');
        DB::statement('ALTER TABLE student_test_attempts MODIFY score_negative_percent decimal(5,2) NULL');
    }

    public function down(): void
    {
        Schema::table('tests', fn (Blueprint $t) => $t->dropColumn(['lesson', 'exam_type', 'total_marks']));
        Schema::table('student_assigned_quizzes', fn (Blueprint $t) => $t->dropColumn(['scheduled_at', 'status']));
        Schema::table('test_questions', fn (Blueprint $t) => $t->dropColumn('points'));
        Schema::table('student_test_attempts', fn (Blueprint $t) => $t->dropColumn(['status', 'started_at', 'score_raw']));
        DB::statement('ALTER TABLE student_test_attempts MODIFY test_id bigint UNSIGNED NULL');
        DB::statement('ALTER TABLE student_test_attempts MODIFY score_simple_percent decimal(5,2) NOT NULL');
        DB::statement('ALTER TABLE student_test_attempts MODIFY score_negative_percent decimal(5,2) NOT NULL');
    }
};
