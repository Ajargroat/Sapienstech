<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The weekly-planner AI assistant: one conversation per consultant per student,
 * its messages, and the monthly token meter that backs the tenant's budget.
 *
 * All three follow schedule_week_notes' conventions exactly — foreignId
 * tenant_id with cascade, and BelongsToTenant on the model side, so no query
 * has to remember the scope. There is deliberately no `student_id`-less shape:
 * a thread is always about one student, which is what makes cross-student
 * leakage structurally impossible rather than merely checked.
 *
 * One migration for the three tables because they are one feature and are
 * created, rolled back and reasoned about together.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedule_assistant_threads')) {
            Schema::create('schedule_assistant_threads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete()
                    ->comment('users.id of the consultant who owns this conversation');
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->date('week_start_date')->nullable()
                    ->comment('Saturday-anchored Persian week under discussion');
                $table->string('title')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamps();

                // "The conversation I have with this student" can never double
                // up. Also the lookup key on every request, so it is indexed in
                // this order rather than as three separate indexes.
                $table->unique(['tenant_id', 'user_id', 'student_id']);
                $table->index(['tenant_id', 'last_activity_at']);
            });
        }

        if (! Schema::hasTable('schedule_assistant_messages')) {
            Schema::create('schedule_assistant_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('thread_id')
                    ->constrained('schedule_assistant_threads')->cascadeOnDelete();
                $table->string('role', 16)->comment('user | assistant');
                $table->text('content')->nullable();

                /*
                 | The suggestions the assistant returned with this message,
                 | validated server-side before they were stored. JSON rather
                 | than its own table on purpose: a suggestion has no
                 | independent lifecycle or query pattern — it is a rendering
                 | artifact of exactly one message, read only when that message
                 | is rendered. (schedule_week_notes became a table because
                 | notes ARE queried per day; these are not queried at all.)
                 */
                $table->json('suggestions')->nullable();

                $table->string('model', 120)->nullable();
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedInteger('latency_ms')->nullable();

                /*
                 | Set when the turn failed (unconfigured|unreachable|
                 | rate_limited|budget_exhausted|bad_request|server_error).
                 | Stored rather than dropped so a failed turn is visible in
                 | history instead of leaving a silent gap the consultant reads
                 | as "the feature is broken".
                 */
                $table->string('error_code', 32)->nullable();

                $table->timestamps();

                $table->index(['thread_id', 'id']);
            });
        }

        if (! Schema::hasTable('schedule_assistant_usages')) {
            Schema::create('schedule_assistant_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('period', 7)->comment('Y-m, e.g. 2026-10');
                $table->unsignedBigInteger('tokens_in')->default(0);
                $table->unsignedBigInteger('tokens_out')->default(0);
                $table->unsignedBigInteger('request_count')->default(0);
                $table->timestamps();

                /*
                 | Its own table rather than an aggregate over messages: the
                 | budget check runs on every request and must be one cheap
                 | indexed lookup, not a SUM across a growing table. It also
                 | survives any future pruning of old messages, so the meter
                 | stays truthful.
                 */
                $table->unique(['tenant_id', 'period']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_assistant_messages');
        Schema::dropIfExists('schedule_assistant_threads');
        Schema::dropIfExists('schedule_assistant_usages');
    }
};
