<?php

namespace Tests\Feature\Lessons;

use App\Models\BookLesson;
use App\Models\Domain;
use App\Models\LessonBlock;
use App\Models\LessonFigure;
use App\Models\LessonImport;
use App\Models\LessonSourcePage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The lesson block editor's contract (PLAN react-02).
 *
 * The screen is now a React island fed by one server-serialised payload, with
 * the Blade rows kept as the pre-mount / JS-off fallback. This file pins the
 * two things a payload refactor can silently break:
 *
 *   1. the prerequisites — `import.page` was missing entirely, so any editor
 *      render for a lesson with source pages threw RouteNotFoundException;
 *   2. the form/save contract — order becomes position, absent rows are
 *      deleted, dirty is fingerprint-based, and a malformed block is a keyed
 *      422 (including in the payload that re-renders the island).
 *
 * It runs on the in-memory sqlite database, like the rest of the lesson suite.
 */
class LessonEditorTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();
        $host = Str::lower(Str::random(10)).'.sapienstech.test';

        Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => $host,
            'is_primary' => true,
        ]);

        Cache::forget("domain:{$host}");

        return [$tenant, $host];
    }

    private function consultantFor(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->consultant()->create(['tenant_id' => $tenant->id]);
    }

    private function teacherFor(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->teacher()->create(['tenant_id' => $tenant->id]);
    }

    private function importFor(Tenant $tenant): LessonImport
    {
        app()->instance('tenant', $tenant);

        return LessonImport::create([
            'book_title' => 'زیست‌شناسی دهم',
            'pdf_path' => 'lessons/1/pdf/book.pdf',
            'status' => 'ready',
            'page_count' => 2,
        ]);
    }

    private function lessonFor(Tenant $tenant, ?LessonImport $import = null, array $attributes = []): BookLesson
    {
        app()->instance('tenant', $tenant);

        $import ??= $this->importFor($tenant);

        return BookLesson::create($attributes + [
            'lesson_import_id' => $import->id,
            'book_title' => $import->book_title,
            'lesson_title' => 'فتوسنتز',
            'status' => 'draft',
            'source_page_from' => 4,
            'source_page_to' => 5,
        ]);
    }

    private function blockFor(
        BookLesson $lesson,
        string $type,
        array $data,
        int $position,
        ?int $sourcePage = null,
        bool $dirty = false,
    ): LessonBlock {
        app()->instance('tenant', $lesson->tenant);

        return LessonBlock::create([
            'lesson_id' => $lesson->id,
            'position' => $position,
            'type' => $type,
            'data' => $data,
            'source_page' => $sourcePage,
            'is_dirty' => $dirty,
            // The AI builder stores the relation too; the preview reads it.
            'figure_id' => $type === 'figure' ? ($data['figure_id'] ?? null) : null,
        ]);
    }

    /** Pull the island props out of the rendered HTML. */
    private function payloadFrom(TestResponse $response): array
    {
        $html = $response->getContent();

        $this->assertSame(
            1,
            preg_match('/<script type="application\/json" data-lesson-editor-payload>(.*?)<\/script>/s', $html, $match),
            'the editor should carry exactly one JSON payload',
        );

        return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function editorUrl(string $host, int $lessonId, string $panel = 'consultant'): string
    {
        return "http://{$host}/{$panel}/lessons/editor/{$lessonId}";
    }

    /* --------------------------------------------------------- prerequisite */

    public function test_the_source_page_route_exists_under_both_panels(): void
    {
        // Without this route, editor.blade.php:100 throws while building the
        // <img src> for any lesson with source pages.
        $this->assertNotNull(Route::getRoutes()->getByName('consultant.lessons.import.page'));
        $this->assertNotNull(Route::getRoutes()->getByName('teacher.lessons.import.page'));
    }

    public function test_the_source_page_route_serves_the_rendered_png(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $import = $this->importFor($tenant);

        $relative = 'test-pages/'.Str::random(8).'.png';
        $slug = $tenant->slug;
        $tenantDir = public_path("tenants/{$slug}");
        $pageDir = public_path("tenants/{$slug}/test-pages");
        $absolute = public_path("tenants/{$slug}/{$relative}");
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=');

        $createdTenantDir = ! is_dir($tenantDir);

        if (! is_dir($pageDir)) {
            mkdir($pageDir, 0755, true);
        }

        file_put_contents($absolute, $png);

        try {
            LessonSourcePage::create([
                'lesson_import_id' => $import->id,
                'page_number' => 4,
                'image_path' => $relative,
                'width' => 1,
                'height' => 1,
                'char_count' => 0,
            ]);

            $url = "http://{$host}/consultant/lessons/import/{$import->id}/page/4";

            $response = $this->actingAs($user)->get($url);

            $response->assertOk()->assertHeader('content-type', 'image/png');
            // BinaryFileResponse has no string content; read the served file.
            $this->assertSame(
                $png,
                file_get_contents($response->baseResponse->getFile()->getPathname()),
            );
        } finally {
            @unlink($absolute);
            @rmdir($pageDir);

            if ($createdTenantDir) {
                @rmdir($tenantDir);
            }
        }
    }

    /* -------------------------------------------------------------- render */

    public function test_the_editor_renders_the_payload_and_the_server_fallback(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $import = $this->importFor($tenant);
        $lesson = $this->lessonFor($tenant, $import);

        foreach ([4, 5] as $page) {
            LessonSourcePage::create([
                'lesson_import_id' => $import->id,
                'page_number' => $page,
                'image_path' => "lessons/{$page}.png",
                'width' => 800,
                'height' => 1100,
                'char_count' => 120,
            ]);
        }

        $figure = LessonFigure::create([
            'lesson_import_id' => $import->id,
            'page_number' => 4,
            'image_path' => 'lessons/fig-4.png',
            'confidence' => 'high',
        ]);

        $this->blockFor($lesson, 'heading', ['text' => 'تیتر', 'level' => 2], 0, 4);
        $this->blockFor($lesson, 'figure', ['figure_id' => $figure->id, 'placement' => 'wide', 'caption' => ''], 1, 4);

        $response = $this->actingAs($user)->get($this->editorUrl($host, $lesson->id));

        $response
            ->assertOk()
            ->assertSee('data-lesson-editor-island', false)
            ->assertSee('data-lesson-editor-payload', false)
            ->assertSee('data-lesson-pages', false)
            ->assertSee('data-lesson-block', false)
            ->assertSee(lessons_route('import.page', [$import, 4]), false);

        $payload = $this->payloadFrom($response);

        $this->assertSame($lesson->id, $payload['lessonId']);
        $this->assertSame('draft', $payload['status']);
        $this->assertStringEndsWith("/consultant/lessons/editor/{$lesson->id}/blocks", $payload['saveUrl']);
        $this->assertNotEmpty($payload['csrfToken']);
        $this->assertCount(2, $payload['blocks']);
        $this->assertSame('heading', $payload['blocks'][0]['type']);
        $this->assertSame('تیتر', $payload['blocks'][0]['data']['text']);
        $this->assertSame(4, $payload['blocks'][0]['sourcePage']);
        $this->assertSame($figure->id, $payload['blocks'][1]['data']['figure_id']);
        $this->assertStringEndsWith('/lessons/fig-4.png', $payload['blocks'][1]['figureUrl']);
        $this->assertCount(2, $payload['pages']);
        $this->assertSame(4, $payload['pages'][0]['number']);
        $this->assertCount(1, $payload['figures']);
        $this->assertSame(7, count($payload['types']));
        $this->assertSame(3, count($payload['placements']));
        $this->assertSame([], $payload['errors']);
    }

    public function test_both_panels_render_the_same_editor_island(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $lesson = $this->lessonFor($tenant);
        $this->blockFor($lesson, 'paragraph', ['text' => 'متن'], 0, 4);

        $consultant = $this->consultantFor($tenant);
        $teacher = $this->teacherFor($tenant);

        $consultantPage = $this->actingAs($consultant)->get($this->editorUrl($host, $lesson->id, 'consultant'));
        $teacherPage = $this->actingAs($teacher)->get($this->editorUrl($host, $lesson->id, 'teacher'));

        $consultantPage->assertOk()->assertSee('data-lesson-editor-island', false);
        $teacherPage->assertOk()->assertSee('data-lesson-editor-island', false);

        $consultantPayload = $this->payloadFrom($consultantPage);
        $teacherPayload = $this->payloadFrom($teacherPage);

        $this->assertSame($consultantPayload['blocks'], $teacherPayload['blocks']);
        $this->assertStringStartsWith('http', $consultantPayload['saveUrl']);
        $this->assertStringContainsString('/consultant/lessons/editor/'.$lesson->id.'/blocks', $consultantPayload['saveUrl']);
        $this->assertStringContainsString('/teacher/lessons/editor/'.$lesson->id.'/blocks', $teacherPayload['saveUrl']);
    }

    /* ---------------------------------------------------------------- save */

    public function test_saving_uses_array_order_and_replaces_the_whole_list(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $lesson = $this->lessonFor($tenant);

        $a = $this->blockFor($lesson, 'paragraph', ['text' => 'a'], 0, 4);
        $b = $this->blockFor($lesson, 'paragraph', ['text' => 'b'], 1, 4);
        $c = $this->blockFor($lesson, 'paragraph', ['text' => 'c'], 2, 5);

        $response = $this->actingAs($user)->post(
            "http://{$host}/consultant/lessons/editor/{$lesson->id}/blocks",
            [
                'blocks' => [
                    ['id' => $b->id, 'type' => 'paragraph', 'data' => ['text' => 'b'], 'source_page' => 4],
                    ['id' => $a->id, 'type' => 'paragraph', 'data' => ['text' => 'a edited'], 'source_page' => 5],
                    ['id' => '', 'type' => 'heading', 'data' => ['text' => 'new', 'level' => 3], 'source_page' => 5],
                ],
            ],
        );

        $response->assertRedirect(lessons_route('editor.edit', $lesson));

        $fresh = $lesson->fresh()->blocks;

        $this->assertSame(['b', 'a edited', 'new'], $fresh->pluck('data.text')->all());
        $this->assertSame([0, 1, 2], $fresh->pluck('position')->all());
        $this->assertSame([4, 5, 5], $fresh->pluck('source_page')->all());
        $this->assertSame([false, true, true], $fresh->pluck('is_dirty')->all());
        $this->assertNull($lesson->fresh()->blocks->firstWhere('data.text', 'c'));

        $this->assertDatabaseMissing('lesson_blocks', ['id' => $c->id]);
        $this->assertSame([$b->id, $a->id, $fresh->last()->id], $fresh->pluck('id')->all());
    }

    public function test_an_unchanged_block_stays_clean_and_a_changed_one_becomes_dirty(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $lesson = $this->lessonFor($tenant);

        $clean = $this->blockFor($lesson, 'paragraph', ['text' => 'same'], 0);
        $alreadyDirty = $this->blockFor($lesson, 'paragraph', ['text' => 'edited'], 1, null, true);

        $this->actingAs($user)->post(
            "http://{$host}/consultant/lessons/editor/{$lesson->id}/blocks",
            [
                'blocks' => [
                    ['id' => $clean->id, 'type' => 'paragraph', 'data' => ['text' => 'same'], 'source_page' => null],
                    ['id' => $alreadyDirty->id, 'type' => 'paragraph', 'data' => ['text' => 'edited'], 'source_page' => null],
                ],
            ],
        )->assertRedirect();

        $this->assertFalse($clean->fresh()->is_dirty);
        $this->assertTrue($alreadyDirty->fresh()->is_dirty);
    }

    /* ----------------------------------------------------------- rejection */

    public function test_a_malformed_block_is_a_keyed_422_and_the_error_reaches_the_payload(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $lesson = $this->lessonFor($tenant);

        $response = $this->actingAs($user)->post(
            "http://{$host}/consultant/lessons/editor/{$lesson->id}/blocks",
            ['blocks' => [['type' => 'paragraph', 'data' => ['text' => '   ']]]],
        );

        $response->assertSessionHasErrors(['blocks.0.data.text']);

        // The redirect back re-renders the island with the same key, so the
        // inline error span exists after React mounts too.
        $page = $this->actingAs($user)->get($this->editorUrl($host, $lesson->id));
        $page->assertOk();

        $payload = $this->payloadFrom($page);

        $this->assertSame('متن پاراگراف لازم است.', $payload['errors']['blocks.0.data.text'] ?? null);
    }

    public function test_missing_block_data_is_a_422_not_a_500(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $lesson = $this->lessonFor($tenant);

        $response = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/lessons/editor/{$lesson->id}/blocks",
            ['blocks' => [['type' => 'paragraph']]],
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('blocks.0.data', $response->json('errors'));
    }

    public function test_a_figure_block_without_a_pick_is_a_keyed_422(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $lesson = $this->lessonFor($tenant);

        // ConvertEmptyStringsToNull turns the browser's empty hidden input
        // into null, so an unpicked figure never reaches the insert as 0.
        $this->actingAs($user)->post(
            "http://{$host}/consultant/lessons/editor/{$lesson->id}/blocks",
            [
                'blocks' => [[
                    'type' => 'figure',
                    'data' => ['figure_id' => '', 'placement' => 'wide', 'caption' => ''],
                ]],
            ],
        )->assertRedirect()->assertSessionHasErrors(['blocks.0.data.figure_id']);

        $this->assertSame(0, $lesson->fresh()->blocks()->count());
    }

    public function test_a_figure_from_another_import_is_a_404(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $lesson = $this->lessonFor($tenant);

        $otherImport = $this->importFor($tenant);
        $foreign = LessonFigure::create([
            'lesson_import_id' => $otherImport->id,
            'page_number' => 1,
            'image_path' => 'other/fig.png',
            'confidence' => 'high',
        ]);

        $this->actingAs($user)->post(
            "http://{$host}/consultant/lessons/editor/{$lesson->id}/blocks",
            [
                'blocks' => [[
                    'type' => 'figure',
                    'data' => ['figure_id' => $foreign->id, 'placement' => 'wide', 'caption' => ''],
                ]],
            ],
        )->assertNotFound();
    }

    public function test_a_block_id_from_another_lesson_is_a_404(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $lesson = $this->lessonFor($tenant);
        $otherLesson = $this->lessonFor($tenant);

        $foreign = $this->blockFor($otherLesson, 'paragraph', ['text' => 'not mine'], 0);

        $this->actingAs($user)->post(
            "http://{$host}/consultant/lessons/editor/{$lesson->id}/blocks",
            [
                'blocks' => [[
                    'id' => $foreign->id,
                    'type' => 'paragraph',
                    'data' => ['text' => 'not mine'],
                ]],
            ],
        )->assertNotFound();

        $this->assertDatabaseHas('lesson_blocks', ['id' => $foreign->id]);
    }
}
