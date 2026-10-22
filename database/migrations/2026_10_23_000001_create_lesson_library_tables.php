<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The book-lesson library: import a textbook PDF once, carry its rendered
 * source pages and auto-cropped figures, publish authored lessons made of
 * ordered blocks, and let the AI draft questions from them.
 *
 * One migration for the seven tables because they are one feature and are
 * created, rolled back and reasoned about together. Conventions copied from
 * 2026_10_21_000001 (schedule assistant): foreignId tenant_id with cascade,
 * and BelongsToTenant on the model side, so no query has to remember the
 * scope. The ONLY deliberate exception in this feature is the question
 * release (App\Support\LessonQuestionRelease) which writes the *shared*
 * questions/answers pool with tenant_id = NULL — that is the existing pool
 * contract, not a new one.
 *
 * The dead `books`/`chapters`/`topics` tables are deliberately NOT used: they
 * model a whole PDF and a three-level taxonomy, with no place for a page
 * range, a published state, blocks or provenance. `book_lessons` carries the
 * book and chapter as strings, which is exactly what the reader's breadcrumb
 * and the bank's `chapter_label` need.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lesson_imports')) {
            Schema::create('lesson_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('book_title', 191);
                $table->string('grade', 32)->nullable()->comment('null = usable for every grade');
                $table->string('pdf_path')->comment('tenant-relative path, see TenantUploads');
                $table->unsignedInteger('page_count')->nullable();
                $table->string('status', 16)->default('queued')
                    ->comment('queued | running | ready | needs_ocr | failed');
                $table->string('structure', 16)->nullable()
                    ->comment('wrapped (usable text layer) | scanned (none) | unknown');

                /*
                 | Per-page character and image counts, figure candidate counts
                 | and the flags the extractor raised. JSON, not columns: it is
                 | a report the staff panel renders, never a filter dimension.
                 */
                $table->json('stats')->nullable();

                $table->string('error_code', 48)->nullable();
                $table->text('error_text')->nullable();
                $table->foreignId('created_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('book_lessons')) {
            Schema::create('book_lessons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('lesson_import_id')->nullable()
                    ->constrained('lesson_imports')->nullOnDelete();
                $table->string('book_title', 191);
                $table->string('chapter_title', 191)->nullable();
                $table->string('lesson_title', 191);
                $table->string('subject', 64)->nullable();
                $table->string('grade', 32)->nullable();
                $table->unsignedInteger('source_page_from')->nullable();
                $table->unsignedInteger('source_page_to')->nullable();
                $table->string('status', 16)->default('draft')
                    ->comment('importing | draft | published');
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('ai_drafted_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'grade']);
            });
        }

        /*
         | Figures before blocks on purpose: lesson_blocks.figure_id references
         | lesson_figures, and MySQL error 1824 ("failed to open the referenced
         | table") is what a foreign key to a not-yet-created table produces.
         | SQLite tolerated the old order, so the bug only surfaced on MySQL.
         */
        if (! Schema::hasTable('lesson_figures')) {
            Schema::create('lesson_figures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('lesson_import_id')->constrained('lesson_imports')->cascadeOnDelete();
                $table->unsignedInteger('page_number');
                $table->string('image_path')->comment('tenant-relative PNG crop');
                $table->string('xref', 32)->nullable()->comment('PDF image xref, for dedupe');
                $table->json('bbox')->nullable();
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('confidence', 8)->default('high')
                    ->comment('high = embedded image, low = vector cluster');
                $table->foreignId('linked_lesson_id')->nullable()
                    ->constrained('book_lessons')->nullOnDelete();
                $table->timestamps();

                $table->index(['lesson_import_id', 'page_number']);
            });
        }

        if (! Schema::hasTable('lesson_blocks')) {
            Schema::create('lesson_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('lesson_id')->constrained('book_lessons')->cascadeOnDelete();

                /*
                 | Order is data, not a unique index: a drag-reorder swaps two
                 | positions in one pass, and a (lesson_id, position) unique key
                 | would reject the intermediate state. Every read orders by
                 | (position, id) instead.
                 */
                $table->unsignedInteger('position')->default(0);

                $table->string('type', 16)
                    ->comment('heading | paragraph | points | equation | tip | figure | figure_duo');
                $table->string('anchor', 64)->nullable()->comment('TOC anchor (heading blocks)');

                /*
                 | The block's own payload (text, items, expression, placement…).
                 | JSON because a block payload has no independent query pattern;
                 | the ROWS are queried (ordered, counted, filtered by is_dirty).
                 */
                $table->json('data');

                $table->unsignedInteger('source_page')->nullable();
                $table->foreignId('figure_id')->nullable()
                    ->constrained('lesson_figures')->nullOnDelete();
                $table->string('confidence', 8)->nullable()->comment('high | medium | low');
                $table->boolean('is_dirty')->default(false)
                    ->comment('a human edited this block after the AI wrote it');
                $table->timestamps();

                $table->index(['lesson_id', 'position']);
            });
        }

        if (! Schema::hasTable('lesson_source_pages')) {
            Schema::create('lesson_source_pages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('lesson_import_id')->constrained('lesson_imports')->cascadeOnDelete();
                $table->unsignedInteger('page_number');
                $table->string('image_path')->comment('tenant-relative PNG path');
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->unsignedInteger('char_count')->default(0);
                $table->json('flags')->nullable();

                /*
                 | The reconstructed text itself, one entry per paragraph. Kept
                 | next to the page (not only inside lesson blocks) so a lesson
                 | can be rebuilt from a page range without re-running python,
                 | and so the AI drafter reads exactly what the extractor saw.
                 */
                $table->json('paragraphs')->nullable();
                $table->timestamps();

                $table->unique(['lesson_import_id', 'page_number']);
            });
        }

        if (! Schema::hasTable('lesson_ai_drafts')) {
            Schema::create('lesson_ai_drafts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('lesson_id')->constrained('book_lessons')->cascadeOnDelete();
                $table->string('kind', 12)->comment('blocks | questions');
                $table->string('status', 16)->default('queued')
                    ->comment('queued | running | ready | failed | refused | approved | rejected | released');

                /*
                 | raw_text keeps the provider's literal reply so a refusal can
                 | be read back; payload is the VALIDATED artifact and is NULL
                 | whenever validation refused (PREFERENCE §7.3: a failed draft
                 | stores NULL, never an unvalidated tree).
                 */
                $table->text('raw_text')->nullable();
                $table->json('payload')->nullable();

                $table->string('error_code', 48)->nullable();
                $table->text('error_text')->nullable();
                $table->string('model', 120)->nullable();
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedInteger('latency_ms')->nullable();
                $table->foreignId('created_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->foreignId('released_question_id')->nullable()
                    ->comment('questions.id written by the release; not an FK, the pool is shared');
                $table->timestamps();

                $table->index(['tenant_id', 'lesson_id', 'kind', 'status']);
            });
        }

        if (! Schema::hasTable('lesson_reading_progress')) {
            Schema::create('lesson_reading_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('lesson_id')->constrained('book_lessons')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('max_section_index')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['lesson_id', 'student_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_reading_progress');
        Schema::dropIfExists('lesson_ai_drafts');
        Schema::dropIfExists('lesson_figures');
        Schema::dropIfExists('lesson_source_pages');
        Schema::dropIfExists('lesson_blocks');
        Schema::dropIfExists('book_lessons');
        Schema::dropIfExists('lesson_imports');
    }
};
