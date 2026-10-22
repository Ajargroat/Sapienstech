<?php

namespace App\Support;

use App\Models\LessonFigure;
use App\Models\LessonImport;
use App\Models\LessonSourcePage;
use App\Support\LessonPdf\ExtractionResult;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Persisting one extraction run: move the worker's PNGs into the tenant's own
 * tree and write lesson_source_pages + lesson_figures against the import.
 *
 * The extractor writes into a scratch directory (the job's), and this class is
 * the only place that decides where the *durable* copy lives — through
 * TenantUploads::pathFor(), so public/tenants/{slug}/ keeps one owner.
 *
 * Idempotent on purpose: a retried job replaces the import's pages and figures
 * instead of doubling them, so a queue that redelivers cannot double a book.
 */
class LessonImportStorage
{
    public function persist(LessonImport $import, ExtractionResult $result): void
    {
        $base = 'lessons/'.$import->id;

        DB::transaction(function () use ($import, $result, $base) {
            LessonSourcePage::query()->where('lesson_import_id', $import->id)->delete();
            LessonFigure::query()->where('lesson_import_id', $import->id)->delete();

            foreach ($result->pages as $page) {
                $relative = $this->place($page['image_path'], $base.'/pages');

                LessonSourcePage::create([
                    'tenant_id' => $import->tenant_id,
                    'lesson_import_id' => $import->id,
                    'page_number' => $page['page_number'],
                    'image_path' => $relative ?? '',
                    'width' => $page['width'],
                    'height' => $page['height'],
                    'char_count' => $page['char_count'],
                    'flags' => $page['flags'],
                    'paragraphs' => $page['paragraphs'],
                ]);
            }

            foreach ($result->figures as $figure) {
                $relative = $this->place($figure['image_path'], $base.'/figures');

                if ($relative === null) {
                    continue;
                }

                LessonFigure::create([
                    'tenant_id' => $import->tenant_id,
                    'lesson_import_id' => $import->id,
                    'page_number' => $figure['page_number'],
                    'image_path' => $relative,
                    'xref' => $figure['xref'],
                    'bbox' => $figure['bbox'],
                    'width' => $figure['width'],
                    'height' => $figure['height'],
                    'confidence' => $figure['confidence'],
                ]);
            }
        });
    }

    /** Copy one worker file into the tenant tree; returns the relative path. */
    private function place(?string $source, string $folder): ?string
    {
        if ($source === null || ! is_file($source)) {
            return null;
        }

        $target = TenantUploads::pathFor($folder, basename($source));

        try {
            if (! @copy($source, $target)) {
                return null;
            }
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($source);
        }

        return TenantUploads::relative($target, $folder);
    }

    /** Delete an import's files (used when the import row is deleted). */
    public function purge(LessonImport $import): void
    {
        $import->sourcePages()->get()->each(fn (LessonSourcePage $page) => TenantUploads::delete($page->image_path));
        $import->figures()->get()->each(fn (LessonFigure $figure) => TenantUploads::delete($figure->image_path));
    }
}
