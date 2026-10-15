<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly consultant notes ("توضیحات هفته").
 *
 * The consultant writes a short brief per weekday; the student reads the
 * current week's notes in their portal. There is deliberately no controller
 * that the student portal writes through -- the table is consultant-authored,
 * student-readable.
 *
 * One row per (student, week_start_date, day_index):
 *  - student_id + week_start_date + day_index is the natural key, so
 *    "the note for Tuesday of this week" can never double up.
 *  - week_start_date follows the same Saturday-anchored Persian week the
 *    schedule grid uses (StudentScheduleController::resolveWeekStart), so a
 *    note and the blocks it describes always agree on which week they mean.
 *  - tenant_id keeps the row inside the tenant the same way schedule_items
 *    does (BelongsToTenant), so no query has to remember the scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schedule_week_notes')) {
            return;
        }

        Schema::create('schedule_week_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('week_start_date');
            $table->unsignedTinyInteger('day_index');
            $table->text('note_text')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'week_start_date', 'day_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_week_notes');
    }
};
