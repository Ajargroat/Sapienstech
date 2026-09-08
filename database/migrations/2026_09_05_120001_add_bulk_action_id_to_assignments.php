<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Back-reference from bulk-created rows to their BulkAction, enabling
 * one-click revert of a whole batch. Nullable: single-student assignments
 * (the existing per-student flows) never belong to a batch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_assigned_quizzes', function (Blueprint $table) {
            $table->foreignId('bulk_action_id')->nullable()
                ->constrained('bulk_actions')->nullOnDelete();
        });

        Schema::table('schedule_items', function (Blueprint $table) {
            $table->foreignId('bulk_action_id')->nullable()
                ->constrained('bulk_actions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_assigned_quizzes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bulk_action_id');
        });

        Schema::table('schedule_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bulk_action_id');
        });
    }
};
