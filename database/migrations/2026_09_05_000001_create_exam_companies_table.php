<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The corporations that run mock exams (قلم‌چی، ماز، سنجش، خیلی‌سبز، گزینه‌دو).
 *
 * Tenant-owned so each consultant curates their own list. `color` tints the
 * report-card badge the same way schedule_items.color tints a calendar block;
 * `slug` is the stable key the report-card filter and badge markup use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('color', 7)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_companies');
    }
};
