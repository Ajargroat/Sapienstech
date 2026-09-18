<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | A recurring weekly class block a teacher owns (Saturday = 0 …
         | Friday = 6, the Persian week). Grade-scoped so students of that
         | class see it on their timetable; null grade = visible to all.
         */
        Schema::create('class_schedules', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('teacher_id')->comment('users.id of the owning teacher');
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('subject', 100)->nullable();
            $t->string('grade', 50)->nullable()->index()->comment('null = every grade');
            $t->unsignedTinyInteger('day_of_week')->comment('0 = Saturday … 6 = Friday');
            $t->time('start_time');
            $t->time('end_time');
            $t->string('room', 50)->nullable();
            $t->string('color', 20)->nullable();
            $t->boolean('is_published')->default(true);
            $t->timestamps();

            $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $t->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
