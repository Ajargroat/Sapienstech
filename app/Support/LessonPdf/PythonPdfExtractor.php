<?php

namespace App\Support\LessonPdf;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * The real extractor: one python process per run, JSON only through files.
 *
 * Three things here are load-bearing and were measured, not guessed:
 *
 *  1. **Args array, never a shell string.** A Persian PDF filename contains
 *     spaces and parentheses («(فارسی، راست‌چین)» is a real filename in this
 *     repo's history); string concatenation into `cmd /c` corrupts it.
 *  2. **File → file JSON.** The Windows console codepage mangles Persian, so
 *     stdout stays ASCII diagnostics and the *result* comes back from a file
 *     read with JSON_THROW_ON_ERROR.
 *  3. **Failure is a result, not an exception.** A missing python, a timeout or
 *     a non-zero exit all become an ExtractionResult with an `error`, so the
 *     import row lands in `failed` with a reason instead of a dead job.
 */
class PythonPdfExtractor implements PdfExtractor
{
    public function __construct(
        private readonly ?string $python = null,
        private readonly int $timeout = 60,
        private readonly int $pageDpi = 150,
        private readonly int $figureDpi = 200,
        private readonly int $maxPages = 400,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            python: (string) site('lessons.pdf_python', env('LESSON_PDF_PYTHON', 'C:\Python314\python.exe')),
            timeout: max(5, (int) site('lessons.extract_timeout', 60)),
            pageDpi: max(50, (int) site('lessons.page_dpi', 150)),
            figureDpi: max(50, (int) site('lessons.figure_dpi', 200)),
            maxPages: max(1, (int) site('lessons.max_pages', 400)),
        );
    }

    public function extract(string $pdfAbsolutePath, string $pagesDir, string $figuresDir): ExtractionResult
    {
        $script = base_path('scripts/pdf/extract.py');

        if (! is_file($script)) {
            return new ExtractionResult(error: 'extractor_script_missing');
        }

        if (! is_file($pdfAbsolutePath)) {
            return new ExtractionResult(error: 'pdf_missing');
        }

        foreach ([$pagesDir, $figuresDir] as $dir) {
            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return new ExtractionResult(error: 'output_dir_unwritable');
            }
        }

        $jobFile = $pagesDir.DIRECTORY_SEPARATOR.'.job-'.bin2hex(random_bytes(6)).'.json';
        $outFile = $pagesDir.DIRECTORY_SEPARATOR.'.result-'.bin2hex(random_bytes(6)).'.json';

        $job = [
            'pdfs' => [[
                'key' => 'import',
                'path' => $pdfAbsolutePath,
                'pages_dir' => $pagesDir,
                'figures_dir' => $figuresDir,
            ]],
            'page_dpi' => $this->pageDpi,
            'figure_dpi' => $this->figureDpi,
            'max_pages' => $this->maxPages,
        ];

        try {
            file_put_contents($jobFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            $process = new Process(
                [$this->python(), $script, '--in', $jobFile, '--out', $outFile],
                base_path(),
                null,
                null,
                (float) $this->timeout,
            );
            $process->run();

            if (! is_file($outFile)) {
                $reason = $process->isSuccessful() ? 'extractor_wrote_no_result' : 'extractor_failed';

                Log::warning('lesson pdf: extractor produced no result', [
                    'exit' => $process->getExitCode(),
                    'stderr' => substr($this->ascii($process->getErrorOutput()), 0, 500),
                ]);

                return new ExtractionResult(error: $reason);
            }

            $decoded = json_decode((string) file_get_contents($outFile), true, 512, JSON_THROW_ON_ERROR);
            $pdf = (array) (($decoded['pdfs'] ?? [])[0] ?? []);

            return ExtractionResult::fromArray($pdf);
        } catch (\JsonException $e) {
            return new ExtractionResult(error: 'extractor_bad_json');
        } catch (\Throwable $e) {
            Log::warning('lesson pdf: extraction failed: '.$e->getMessage());

            return new ExtractionResult(error: 'extractor_unreachable');
        } finally {
            @unlink($jobFile);
            @unlink($outFile);
        }
    }

    /** Absolute path of the interpreter; empty config falls back to the default. */
    private function python(): string
    {
        $python = trim((string) $this->python);

        return $python !== '' ? $python : 'python';
    }

    /** Diagnostics only — never rendered, never carrying Persian into a view. */
    private function ascii(string $value): string
    {
        return (string) preg_replace('/[^\x20-\x7E\s]/', '?', $value);
    }

    public static function assertUsable(string $python): void
    {
        if (trim($python) === '') {
            throw new RuntimeException('No python interpreter configured for lesson extraction.');
        }
    }
}
