<?php

namespace Tests\Feature\Lessons;

use App\Jobs\ExtractLessonPdf;
use App\Models\LessonImport;
use App\Models\Tenant;
use App\Support\LessonImportStorage;
use App\Support\LessonPdf\PdfExtractor;
use App\Support\LessonPdf\PythonPdfExtractor;
use App\Support\Lessons\StatusLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pipeline's wiring — what a unit test with a fake extractor cannot see.
 *
 * This file exists because of a defect that shipped: every lesson test
 * substituted a fake `PdfExtractor`, so nothing ever asked the *container* for
 * the real one. The interface had no binding, the worker died with
 *
 *     Target [App\Support\LessonPdf\PdfExtractor] is not instantiable
 *
 * before `handle()` ran, the import row never left `queued`, and the show page
 * sat on «در حال استخراج صفحه‌ها…» forever with no error anywhere the user
 * could see. The fake made the suite green and the feature dead.
 *
 * So these tests resolve the interface the way the worker does, and pin the
 * properties that make the worker safe.
 */
class LessonPipelineWiringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The regression itself: the interface must resolve, to the real extractor.
     */
    public function test_the_extractor_interface_resolves_to_the_python_extractor(): void
    {
        $resolved = app(PdfExtractor::class);

        $this->assertInstanceOf(PythonPdfExtractor::class, $resolved);
    }

    /**
     * Resolution must happen per call, not once at boot: the python path,
     * timeout and DPI are per-tenant site settings, and the tenant is only
     * bound when the job runs. A singleton would freeze the first tenant's
     * config for every later tenant on the same worker.
     */
    public function test_the_extractor_is_not_a_singleton(): void
    {
        $this->assertNotSame(app(PdfExtractor::class), app(PdfExtractor::class));
    }

    /**
     * A job whose `$timeout` meets or exceeds the connection's `retry_after`
     * gets picked up again while it is still running, and a killed extraction
     * re-renders a whole book. The job's class docblock promises a guard test;
     * this is it. Checked against every connection that declares a
     * `retry_after` (§7.8), because the default one under test is `database`.
     */
    public function test_the_job_timeout_is_below_every_queue_retry_after(): void
    {
        $timeout = (new ExtractLessonPdf(1, 1))->timeout;
        $checked = 0;

        foreach ((array) config('queue.connections') as $name => $connection) {
            if (! is_array($connection) || ! isset($connection['retry_after'])) {
                continue;
            }

            $retryAfter = (int) $connection['retry_after'];

            $this->assertGreaterThan(0, $retryAfter, "Queue `{$name}` declares retry_after=0.");
            $this->assertLessThan(
                $retryAfter,
                $timeout,
                "ExtractLessonPdf::\$timeout ({$timeout}s) must stay below queue `{$name}` retry_after ({$retryAfter}s).",
            );

            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'No queue connection declares retry_after — the guard is vacuous.');
    }

    /**
     * The job handles failure inside `handle()` and re-throws a crash. With
     * retries enabled a crash would re-render the book; it must stay at one try.
     */
    public function test_the_extraction_job_does_not_retry(): void
    {
        $this->assertSame(1, (new ExtractLessonPdf(1, 1))->tries);
    }

    /**
     * A status the import row can hold must have Persian copy, or the staff page
     * prints the raw enum member. Asserted against the IMPORT map — the row's
     * status comes from `import()`, not `lesson()` (which labels `book_lessons`).
     */
    public function test_every_import_status_has_a_label(): void
    {
        $labels = StatusLabels::import();

        foreach (['queued', 'running', 'ready', 'needs_ocr', 'failed'] as $status) {
            $this->assertArrayHasKey($status, $labels, "Import status `{$status}` renders raw.");
            $this->assertNotSame('', trim($labels[$status]), "Import status `{$status}` has an empty label.");
        }
    }

    /**
     * `queued` is the only status the show page polls on, and a row stuck there
     * is exactly the symptom the user reported. The worker must be the thing
     * that moves a row off it, so pin that the status the controller writes on
     * dispatch is one the job always overwrites.
     */
    public function test_a_queued_import_is_advanced_by_the_job(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $import = LessonImport::create([
            'tenant_id' => $tenant->id,
            'book_title' => 'probe',
            'pdf_path' => 'lessons/pdf/missing.pdf',
            'status' => 'queued',
        ]);

        // The PDF is absent on purpose: the job must still reach a TERMINAL
        // state rather than leaving the row for the poller to spin on.
        (new ExtractLessonPdf((int) $tenant->id, (int) $import->id))
            ->handle(app(PdfExtractor::class), new LessonImportStorage);

        $import->refresh();

        $this->assertNotSame('queued', $import->status, 'A missing PDF left the import queued.');
        $this->assertSame('failed', $import->status);
        $this->assertSame('pdf_missing', $import->error_code);
        $this->assertNotNull($import->finished_at);
    }
}
