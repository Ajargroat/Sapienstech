<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of whether a question counted toward the score.
 *
 * Questions with no stored correct answer stay in the bank and stay
 * selectable, but are excluded from the denominator and never marked
 * right/wrong. The flag is snapshotted per attempt row because the importer
 * is re-runnable: deriving it from the bank at read time would silently
 * rewrite history the next time the bank is refreshed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempt_answers', function (Blueprint $t) {
            if (! Schema::hasColumn('attempt_answers', 'is_graded')) {
                $t->boolean('is_graded')->default(true)->after('is_correct');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attempt_answers', fn (Blueprint $t) => $t->dropColumn('is_graded'));
    }
};
