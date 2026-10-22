<?php

namespace App\Jobs;

use App\Models\LessonImport;
use App\Models\Tenant;
use App\Support\LessonImportStorage;
use App\Support\LessonPdf\ExtractionResult;
use App\Support\LessonPdf\PdfExtractor;
use App\Support\TenantUploads;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs one book import: bind the tenant, extract, move the files into the
 * tenant tree, and land the row in ready | needs_ocr | failed.
 *
 * The payload carries the tenant's PRIMARY KEY, never a model. A queued tenant
 * snapshot is a stale object by the time a worker picks the job up, and every
 * tenancy decision inside here — BelongsToTenant's scope, TenantUploads' slug,
 * the AiBudget meter — reads the *bound* tenant.
 *
 * `$timeout` MUST stay below the queue connection's `retry_after`
 * (PREFERENCE §7.3): a retried extraction would re-render a whole book.
 * LessonPipelineGuardsTest pins the relation against the live config.
 */
class ExtractLessonPdf implements ShouldQueue
{
    use Queueable;

    public int $timeout = 28;

    public int $tries = 1;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $importId,
    ) {}

    public static function for(LessonImport $import): self
    {
        return new self((int) $import->tenant_id, (int) $import->id);
    }

    public function handle(PdfExtractor $extractor, LessonImportStorage $storage): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        app()->instance('tenant', $tenant);

        $import = LessonImport::query()->findOrFail($this->importId);

        $import->update([
            'status' => 'running',
            'started_at' => now(),
            'error_code' => null,
            'error_text' => null,
        ]);

        try {
            $pdf = $this->pdfAbsolutePath($import);

            if ($pdf === null) {
                $this->fail($import, new ExtractionResult(error: 'pdf_missing'));

                return;
            }

            $work = $this->workDirectory($import);

            $result = $extractor->extract(
                $pdf,
                $work.'/pages',
                $work.'/figures',
            );

            if ($result->failed()) {
                $this->fail($import, $result);

                return;
            }

            $storage->persist($import, $result);

            $import->update([
                'status' => $result->status(),
                'structure' => $result->structure,
                'page_count' => max($result->pageCount, count($result->pages)),
                'stats' => $result->stats(),
                'error_code' => $result->status() === 'needs_ocr' ? 'no_text_layer' : null,
                'error_text' => $result->status() === 'needs_ocr'
                    ? 'This PDF has no text layer; its pages are rendered but the text must be typed or OCR-installed.'
                    : null,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $import->update([
                'status' => 'failed',
                'error_code' => 'extraction_crashed',
                'error_text' => mb_substr($e->getMessage(), 0, 500),
                'finished_at' => now(),
            ]);

            throw $e;
        } finally {
            $this->cleanWork($import);
        }
    }

    /** A terminal row is always better than a job that vanished. */
    private function fail(LessonImport $import, ExtractionResult $result): void
    {
        $import->update([
            'status' => 'failed',
            'error_code' => mb_substr((string) $result->error, 0, 48),
            'error_text' => $result->error,
            'finished_at' => now(),
        ]);
    }

    private function pdfAbsolutePath(LessonImport $import): ?string
    {
        $relative = ltrim((string) $import->pdf_path, '/');
        $absolute = public_path('tenants/'.$import->tenant->slug.'/'.$relative);

        return is_file($absolute) ? $absolute : null;
    }

    private function workDirectory(LessonImport $import): string
    {
        $probe = TenantUploads::pathFor('lessons/'.$import->id.'/work', '.keep');
        @unlink($probe);

        return dirname($probe);
    }

    private function cleanWork(LessonImport $import): void
    {
        $dir = $this->workDirectory($import);

        if (! is_dir($dir)) {
            return;
        }

        foreach ((array) glob($dir.'/{,*/}*', GLOB_BRACE) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($dir.'/pages');
        @rmdir($dir.'/figures');
        @rmdir($dir);
    }
}
