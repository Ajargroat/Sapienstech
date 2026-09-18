<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_materials', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('teacher_id')->comment('users.id of the uploading teacher');
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('subject', 100)->nullable()->index();
            $t->string('grade', 50)->nullable()->index();
            $t->string('file_path')->comment('Relative path under the tenant asset tree');
            $t->string('file_name')->comment('Original filename, shown in the UI');
            $t->unsignedBigInteger('file_size')->default(0);
            $t->string('mime_type', 120)->nullable();
            $t->unsignedInteger('download_count')->default(0);
            $t->boolean('is_published')->default(true);
            $t->timestamps();

            $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $t->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_materials');
    }
};
