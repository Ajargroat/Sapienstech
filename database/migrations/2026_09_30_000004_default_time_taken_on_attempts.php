<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attempts can now be created *before* they are finished.
 *
 * Until the student runner existed, an attempt row was only ever written at
 * submit time, so the base schema could demand a duration and could stamp
 * completed_at with useCurrent(). Starting the clock on open() broke both
 * assumptions: the insert failed on the NOT NULL, and an in-progress attempt
 * was immediately dated as if it had finished.
 *
 * time_taken_seconds keeps NOT NULL with a default of 0 — an attempt that has
 * not been submitted has genuinely taken no time yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE student_test_attempts MODIFY time_taken_seconds int NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE student_test_attempts MODIFY completed_at timestamp NULL DEFAULT NULL');
        } else {
            Schema::table('student_test_attempts', function (Blueprint $t) {
                $t->integer('time_taken_seconds')->default(0)->change();
                $t->timestamp('completed_at')->nullable()->default(null)->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE student_test_attempts MODIFY time_taken_seconds int NOT NULL');
            DB::statement('ALTER TABLE student_test_attempts MODIFY completed_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP');
        } else {
            Schema::table('student_test_attempts', function (Blueprint $t) {
                $t->integer('time_taken_seconds')->default(null)->change();
                $t->timestamp('completed_at')->useCurrent()->change();
            });
        }
    }
};
