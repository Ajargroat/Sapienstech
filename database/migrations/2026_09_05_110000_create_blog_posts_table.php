<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real blog posts, replacing the config-only placeholders.
 *
 * cover_image_path is nullable on purpose: posts render with or without a
 * picture (the landing variants already handle the empty case). The slug is
 * globally unique because the public route binds it without tenant context
 * in the URL; tenant isolation stays on every query via BelongsToTenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Two legacy tables (blog_posts with consultant_id/content/visibility,
        // and its child blog_post_images) predate the token/tenant system.
        // Both are empty and referenced by no code; they were never captured
        // in a migration. We replace them with the real schema. Child is
        // dropped first because it holds an FK to blog_posts. Guarded so a
        // fresh database (where they never existed) is unaffected.
        if (Schema::hasTable('blog_post_images')) {
            Schema::drop('blog_post_images');
        }

        if (Schema::hasTable('blog_posts') && Schema::hasColumn('blog_posts', 'consultant_id')) {
            Schema::drop('blog_posts');
        }

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
