<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The exam window (explicit start + end) and the optional exam cover.
 *
 * The window lives on the assignment beside scheduled_at: bulk assigns one
 * test to many students with per-assignment timing, and a NULL end keeps
 * legacy/bulk rows working ("open once started"). The cover belongs to the
 * exam itself, so it stays consistent across every student it is assigned to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_assigned_quizzes', function (Blueprint $t) {
            if (! Schema::hasColumn('student_assigned_quizzes', 'ends_at')) {
                $t->dateTime('ends_at')->nullable()->after('scheduled_at');
            }
        });

        Schema::table('tests', function (Blueprint $t) {
            if (! Schema::hasColumn('tests', 'cover_image_path')) {
                $t->string('cover_image_path')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_assigned_quizzes', fn (Blueprint $t) => $t->dropColumn('ends_at'));
        Schema::table('tests', fn (Blueprint $t) => $t->dropColumn('cover_image_path'));
    }
};
