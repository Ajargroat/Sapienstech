<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct chat — conversations, participants and messages.
 *
 * Conversation shapes (the invariant that makes "no student↔student DM"
 * structural rather than a runtime check):
 *   - type=direct : exactly one staff actor (staff_user_id) + exactly one
 *                   student (student_id); the participants table mirrors both.
 *   - type=group  : one staff owner (staff_user_id, role=owner) + N students
 *                   (participants rows). student_id is NULL on the row itself.
 * A conversation therefore ALWAYS has exactly one staff actor and zero or
 * one "solo" student, and students are only ever attached through
 * chat_participants. There is no way to express student↔student in this
 * schema.
 *
 * Actor references keep the dual-FK-column pattern used by item_comments
 * (users.id and students.id are independent sequences that collide, so a
 * single polymorphic id is unsafe).
 *
 * last_message_id is a denormalized pointer for conversation-list rendering.
 * It is intentionally NOT a foreign key: chat_messages already references
 * chat_conversations, and a second FK between the two tables would be a
 * circular dependency across both supported drivers (MySQL and SQLite).
 *
 * Portability: written against Laravel's query builder so it runs identically
 * on MySQL (dev/prod) and SQLite (tests); no raw driver SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_conversations')) {
            Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['direct', 'group'])->default('direct');
            $table->string('title')->nullable(); // group name; direct uses peer-derived titles
            $table->foreignId('staff_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('student_id')->nullable()
                ->constrained('students')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete(); // group creator
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['staff_user_id', 'last_message_at']);
            $table->index(['student_id', 'last_message_at']);
            });
        }

        if (! Schema::hasTable('chat_participants')) {
            Schema::create('chat_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')
                ->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()
                ->constrained('students')->cascadeOnDelete();
            $table->enum('role', ['owner', 'member'])->default('member');
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            // NULLs never collide in unique indexes, so the two partial
            // uniques together guarantee "one row per actor per conversation".
            $table->unique(['conversation_id', 'user_id']);
            $table->unique(['conversation_id', 'student_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'student_id']);
            });
        }

        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')
                ->constrained('chat_conversations')->cascadeOnDelete();
            $table->enum('type', ['text', 'system'])->default('text');
            $table->text('body')->nullable();
            $table->foreignId('sender_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('sender_student_id')->nullable()
                ->constrained('students')->nullOnDelete();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); // "delete for everyone" → tombstone rendering

            $table->index(['conversation_id', 'id']);
            $table->index(['tenant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_participants');
        Schema::dropIfExists('chat_conversations');
    }
};
