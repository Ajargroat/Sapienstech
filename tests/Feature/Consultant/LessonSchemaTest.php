<?php

namespace Tests\Feature\Consultant;

use App\Models\BookLesson;
use App\Models\LessonBlock;
use App\Models\LessonImport;
use App\Models\LessonSourcePage;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * S1 — the lesson schema and its two hard contracts:
 *   1. a lesson's blocks come back in (position, id) order, always;
 *   2. BookLesson::visibleTo is the SAME grade/published filter the material
 *      library uses — a draft is invisible and a different grade is invisible.
 *
 * Runs on the in-memory sqlite database, which is the only DB this machine has.
 */
class LessonSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seven_lesson_tables_exist(): void
    {
        foreach ([
            'lesson_imports',
            'book_lessons',
            'lesson_blocks',
            'lesson_source_pages',
            'lesson_figures',
            'lesson_ai_drafts',
            'lesson_reading_progress',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} should exist");
        }
    }

    public function test_blocks_come_back_ordered_by_position_then_id(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $lesson = BookLesson::create([
            'book_title' => 'زیست‌شناسی دهم',
            'lesson_title' => 'فتوسنتز',
            'status' => 'draft',
        ]);

        // Deliberately inserted out of order, and two blocks share a position.
        foreach ([
            [2, 'paragraph', ['text' => 'سوم']],
            [0, 'heading', ['text' => 'اول']],
            [1, 'tip', ['text' => 'دوم']],
            [2, 'paragraph', ['text' => 'چهارم']],
        ] as [$position, $type, $data]) {
            LessonBlock::create([
                'lesson_id' => $lesson->id,
                'position' => $position,
                'type' => $type,
                'data' => $data,
            ]);
        }

        $texts = $lesson->fresh()->blocks->pluck('data.text')->all();

        $this->assertSame(['اول', 'دوم', 'سوم', 'چهارم'], $texts);
    }

    public function test_visible_to_is_published_and_grade_matched(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'grade' => 'دهم']);

        $make = fn (string $status, ?string $grade) => BookLesson::create([
            'book_title' => 'کتاب',
            'lesson_title' => 'درس',
            'status' => $status,
            'grade' => $grade,
        ]);

        $visible = $make('published', 'دهم');
        $allGrades = $make('published', null);
        $otherGrade = $make('published', 'یازدهم');
        $draft = $make('draft', 'دهم');

        $ids = BookLesson::query()->visibleTo($student)->pluck('id')->all();

        $this->assertContains($visible->id, $ids);
        $this->assertContains($allGrades->id, $ids);
        $this->assertNotContains($otherGrade->id, $ids);
        $this->assertNotContains($draft->id, $ids);
    }

    public function test_an_import_owns_its_pages_figures_and_lessons(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $import = LessonImport::create([
            'book_title' => 'زیست‌شناسی دهم',
            'pdf_path' => 'lessons/1/pdf/book.pdf',
            'status' => 'ready',
            'structure' => 'wrapped',
            'stats' => ['pages' => 2],
        ]);

        LessonSourcePage::create([
            'lesson_import_id' => $import->id,
            'page_number' => 2,
            'image_path' => 'lessons/1/pages/2.png',
            'char_count' => 400,
            'flags' => ['medium'],
        ]);
        LessonSourcePage::create([
            'lesson_import_id' => $import->id,
            'page_number' => 1,
            'image_path' => 'lessons/1/pages/1.png',
            'char_count' => 500,
        ]);

        $this->assertSame([1, 2], $import->fresh()->sourcePages->pluck('page_number')->all());
        $this->assertSame(['pages' => 2], $import->fresh()->stats);
        $this->assertFalse($import->fresh()->isRunning());
    }

    public function test_reordering_two_blocks_never_hits_a_unique_index(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $lesson = BookLesson::create(['book_title' => 'b', 'lesson_title' => 'l', 'status' => 'draft']);
        $a = LessonBlock::create(['lesson_id' => $lesson->id, 'position' => 0, 'type' => 'paragraph', 'data' => ['text' => 'a']]);
        $b = LessonBlock::create(['lesson_id' => $lesson->id, 'position' => 1, 'type' => 'paragraph', 'data' => ['text' => 'b']]);

        // The intermediate state every swap passes through.
        $a->update(['position' => 1]);
        $b->update(['position' => 0]);

        $this->assertSame(['b', 'a'], $lesson->fresh()->blocks->pluck('data.text')->all());
    }
}
