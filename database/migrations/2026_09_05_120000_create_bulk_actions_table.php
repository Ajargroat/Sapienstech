<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A recorded bulk operation ("اقدامات گروهی"): one exam assignment or one
 * schedule assignment applied to many students at once.
 *
 * The row exists so the created children (student_assigned_quizzes /
 * schedule_items) carry a bulk_action_id back-reference, which turns "undo
 * this batch" into a single scoped delete instead of a manual hunt. `summary`
 * freezes what was done (test id / block template / filter used) for the
 * history view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->enum('kind', ['exam', 'schedule']);
            $table->json('summary');
            $table->unsignedInteger('affected_count')->default(0);
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_actions');
    }
};
