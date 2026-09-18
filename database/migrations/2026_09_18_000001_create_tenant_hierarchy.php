<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('hierarchy_type')->nullable();
        });

        // Composite candidate keys let both SQLite and MySQL enforce tenant identity.
        foreach (['users', 'students'] as $parent) {
            Schema::table($parent, function (Blueprint $table) use ($parent) {
                $table->unique(['tenant_id', 'id'], "{$parent}_hierarchy_tenant_id_unique");
            });
        }

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 50);
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['tenant_id', 'grade', 'name']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('classroom_student', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('classroom_id');
            $table->unsignedBigInteger('student_id');
            $table->primary(['tenant_id', 'classroom_id', 'student_id']);
            $table->index(['tenant_id', 'student_id']);
            $table->foreign(['tenant_id', 'classroom_id'])->references(['tenant_id', 'id'])
                ->on('classrooms')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'student_id'])->references(['tenant_id', 'id'])
                ->on('students')->cascadeOnDelete();
        });

        Schema::create('classroom_teacher', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('classroom_id');
            $table->unsignedBigInteger('user_id');
            $table->string('subject', 100);
            $table->primary(['tenant_id', 'classroom_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->foreign(['tenant_id', 'classroom_id'])->references(['tenant_id', 'id'])
                ->on('classrooms')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])
                ->on('users')->cascadeOnDelete();
        });

        Schema::create('student_staff', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['tenant_id', 'student_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->foreign(['tenant_id', 'student_id'])->references(['tenant_id', 'id'])
                ->on('students')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])
                ->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_staff');
        Schema::dropIfExists('classroom_teacher');
        Schema::dropIfExists('classroom_student');
        Schema::dropIfExists('classrooms');

        foreach (['users', 'students'] as $parent) {
            Schema::table($parent, function (Blueprint $table) use ($parent) {
                $table->dropUnique("{$parent}_hierarchy_tenant_id_unique");
            });
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('hierarchy_type');
        });
    }
};
