<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base schema for the tables that predate the migration system.
 *
 * These tables were created manually (see "database dump/") before migrations
 * were introduced, so a fresh `php artisan migrate` — e.g. the in-memory
 * SQLite database used by the test suite — had no way to build them.
 *
 * Every table is created only when missing, so this is a no-op on the live
 * database where the tables already exist. Later migrations adjust this
 * schema (added columns, widened enums, replaced legacy blog tables); where
 * a later migration intentionally changes a definition AND cannot run on
 * SQLite (MySQL-only MODIFY statements, guarded per-driver there), the base
 * definition already matches the post-migration intent:
 *   - questions.chapter_id is nullable (imported questions may lack a chapter)
 *   - questions.supabase_id is varchar(64)
 *   - student_test_attempts.score_* are nullable, test_id stays nullable
 *
 * Driver CHECK constraints from MySQL (role/tenant consistency, commenter
 * duality, json_valid) are deliberately not reproduced: the application
 * enforces those invariants, and keeping the migration portable lets the
 * suite run on both MySQL and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->enum('status', ['trial', 'active', 'suspended'])->default('trial');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('domains')) {
            Schema::create('domains', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('domain')->unique();
                $table->boolean('is_primary')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                // NULL only for platform_admin; enforced by the app.
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('email');
                $table->string('password');
                $table->enum('role', ['platform_admin', 'tenant_admin', 'consultant_staff'])
                    ->default('consultant_staff');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'email']);
            });
        }

        if (! Schema::hasTable('students')) {
            Schema::create('students', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('email');
                $table->string('password');
                $table->string('grade', 50)->nullable();
                $table->string('gender', 50)->nullable();
                $table->string('major', 50)->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'email']);
            });
        }

        if (! Schema::hasTable('books')) {
            Schema::create('books', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('book_title');
                $table->string('pdf_file_path')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chapters')) {
            Schema::create('chapters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('book_id')->constrained()->cascadeOnDelete();
                $table->integer('chapter_number');
                $table->string('chapter_title');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('topics')) {
            Schema::create('topics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
                $table->string('topic_title');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('questions')) {
            Schema::create('questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                // Nullable on purpose: imported questions may not be tied to a
                // book chapter yet (see 2026_09_02_130742 migration).
                $table->foreignId('chapter_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
                $table->string('supabase_id', 64)->nullable()->unique();
                $table->text('question_text');
                $table->string('question_image_path')->nullable();
                $table->text('solution_text')->nullable();
                $table->string('solution_image_path')->nullable();
                $table->string('question_number_in_book', 50)->nullable();
                $table->enum('difficulty', ['Easy', 'Medium', 'Hard'])->nullable();
                $table->string('question_type', 50)->default('multiple_choice');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('answers')) {
            Schema::create('answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('question_id')->constrained()->cascadeOnDelete();
                $table->text('answer_text');
                $table->string('answer_image_path')->nullable();
                $table->boolean('is_correct')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tests')) {
            Schema::create('tests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('test_title');
                $table->integer('time_limit_minutes')->nullable();
                $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('test_questions')) {
            Schema::create('test_questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('test_id')->constrained()->cascadeOnDelete();
                $table->foreignId('question_id')->constrained()->cascadeOnDelete();
                $table->integer('position')->default(0);

                $table->unique(['test_id', 'question_id']);
            });
        }

        if (! Schema::hasTable('student_assigned_quizzes')) {
            Schema::create('student_assigned_quizzes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('test_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('assigned_at')->useCurrent();
                $table->boolean('is_completed')->default(false);
            });
        }

        if (! Schema::hasTable('student_test_attempts')) {
            Schema::create('student_test_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assignment_id')->nullable()
                    ->constrained('student_assigned_quizzes')->nullOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('test_id')->nullable()->constrained()->nullOnDelete();
                // Nullable: the 2026_09_02_122325 migration relaxes these on
                // MySQL (a no-op MODIFY there); SQLite skips that statement.
                $table->decimal('score_simple_percent', 5, 2)->nullable();
                $table->decimal('score_negative_percent', 5, 2)->nullable();
                $table->integer('time_taken_seconds');
                $table->timestamp('completed_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('attempt_answers')) {
            Schema::create('attempt_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('attempt_id')->constrained('student_test_attempts')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('question_id')->constrained()->cascadeOnDelete();
                $table->foreignId('chosen_answer_id')->nullable()
                    ->constrained('answers')->cascadeOnDelete();
                $table->boolean('is_correct')->default(false);
                $table->timestamp('answered_at')->useCurrent();

                $table->unique(['attempt_id', 'question_id']);
            });
        }

        if (! Schema::hasTable('schedule_events')) {
            Schema::create('schedule_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('event_title');
                $table->text('event_description')->nullable();
                $table->dateTime('start_datetime');
                $table->dateTime('end_datetime');
                $table->tinyInteger('day_of_week');
                $table->string('event_color', 7)->default('#007bff');
                $table->boolean('is_completed')->default(false);
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('event_comments')) {
            Schema::create('event_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->constrained('schedule_events')->cascadeOnDelete();
                $table->enum('commenter_type', ['user', 'student']);
                $table->foreignId('commenter_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->foreignId('commenter_student_id')->nullable()
                    ->constrained('students')->nullOnDelete();
                $table->text('comment_text');
                $table->timestamp('commented_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('schedule_items')) {
            Schema::create('schedule_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->date('week_start_date');
                $table->string('title');
                $table->text('description')->nullable();
                $table->dateTime('start_datetime');
                $table->dateTime('end_datetime');
                $table->string('color', 50);
                $table->enum('item_type', ['consultant_event', 'student_personal_block']);
                $table->enum('created_by_type', ['user', 'student']);
                $table->foreignId('created_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->foreignId('created_by_student_id')->nullable()
                    ->constrained('students')->nullOnDelete();
                $table->string('link_url', 2083)->nullable();
                $table->string('book_name')->nullable();
                $table->integer('test_count')->nullable();
                $table->integer('page_count')->nullable();
                $table->boolean('is_completed')->default(false);
                $table->dateTime('completion_timestamp')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('item_comments')) {
            Schema::create('item_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('schedule_items')->cascadeOnDelete();
                $table->enum('commenter_type', ['user', 'student']);
                $table->foreignId('commenter_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->foreignId('commenter_student_id')->nullable()
                    ->constrained('students')->nullOnDelete();
                $table->text('comment_text');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contents')) {
            Schema::create('contents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('key', 100);
                $table->string('title')->nullable();
                $table->longText('body')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'key']);
            });
        }

        if (! Schema::hasTable('features')) {
            Schema::create('features', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('key', 100);
                $table->boolean('enabled')->default(false);
                $table->timestamps();

                $table->unique(['tenant_id', 'key']);
            });
        }

        if (! Schema::hasTable('website_configs')) {
            Schema::create('website_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('theme', 100)->default('default');
                $table->string('logo_path')->nullable();
                $table->string('favicon_path')->nullable();
                $table->string('primary_color', 7)->nullable();
                $table->string('secondary_color', 7)->nullable();
                $table->string('font', 100)->nullable();
                $table->longText('layout_config')->nullable();
                $table->timestamps();

                $table->unique('tenant_id');
            });
        }

        if (! Schema::hasTable('consultant_student_assignments')) {
            Schema::create('consultant_student_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->timestamp('assigned_at')->useCurrent();

                $table->unique(['user_id', 'student_id']);
            });
        }

        if (! Schema::hasTable('student_book_permissions')) {
            Schema::create('student_book_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('book_id')->constrained()->cascadeOnDelete();
                $table->timestamp('granted_at')->useCurrent();

                $table->unique(['student_id', 'book_id']);
            });
        }

        if (! Schema::hasTable('student_flagged_questions')) {
            Schema::create('student_flagged_questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('question_id')->constrained()->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['student_id', 'question_id']);
            });
        }

        if (! Schema::hasTable('student_weekly_settings')) {
            Schema::create('student_weekly_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->date('week_start_date');
                $table->longText('display_config')->nullable();
                $table->foreignId('last_modified_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('updated_at')->useCurrent();

                $table->unique(['student_id', 'week_start_date']);
            });
        }

        // Legacy blog shape. The 2026_09_05_110000 migration replaces both
        // tables with the real schema (it drops them when the legacy
        // consultant_id column is present), so fresh databases converge.
        if (! Schema::hasTable('blog_posts')) {
            Schema::create('blog_posts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('consultant_id')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->text('content');
                $table->enum('visibility', ['public', 'students_only'])->default('public');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('blog_post_images')) {
            Schema::create('blog_post_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
                $table->string('image_path');
                $table->timestamp('uploaded_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'blog_post_images',
            'blog_posts',
            'student_weekly_settings',
            'student_flagged_questions',
            'student_book_permissions',
            'consultant_student_assignments',
            'website_configs',
            'features',
            'contents',
            'item_comments',
            'schedule_items',
            'event_comments',
            'schedule_events',
            'attempt_answers',
            'student_test_attempts',
            'student_assigned_quizzes',
            'test_questions',
            'tests',
            'answers',
            'questions',
            'topics',
            'chapters',
            'books',
            'students',
            'users',
            'domains',
            'tenants',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
