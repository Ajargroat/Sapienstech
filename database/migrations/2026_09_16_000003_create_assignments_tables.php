<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | An assignment targets a grade (a class) via its `grade` column,
         | exactly the way students are organized on this platform. The
         | per-student lifecycle rows are created lazily (a student without
         | a row is implicitly 'pending'), so a grade-wide assignment never
         | needs mass inserts.
         */
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('teacher_id')->comment('users.id of the owning teacher');
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('subject', 100)->nullable()->index();
            $t->string('grade', 50)->nullable()->index()->comment('null = every grade');
            $t->timestamp('due_at')->nullable()->index();
            $t->decimal('max_score', 6, 2)->nullable();
            $t->boolean('is_published')->default(true);
            $t->timestamps();

            $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $t->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('assignment_students', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('assignment_id');
            $t->unsignedBigInteger('student_id');
            $t->enum('status', ['pending', 'submitted', 'completed'])->default('pending');
            $t->timestamp('submitted_at')->nullable();
            $t->text('note')->nullable()->comment('Student submission note');
            $t->decimal('score', 6, 2)->nullable();
            $t->timestamps();

            $t->unique(['assignment_id', 'student_id']);
            $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $t->foreign('assignment_id')->references('id')->on('assignments')->cascadeOnDelete();
            $t->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_students');
        Schema::dropIfExists('assignments');
    }
};
