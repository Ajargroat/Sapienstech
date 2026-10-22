<?php

namespace App\Console\Commands;

use App\Jobs\ExtractLessonPdf;
use App\Models\LessonImport;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUploads;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Import a textbook PDF from the shell — the same path the staff panel uses,
 * without HTTP and without the queue (the job runs inline here, because a
 * command is already a worker).
 *
 * Deliberately named after the tenant so a mismatched binding is impossible:
 * the command resolves the tenant by slug, binds it, and THEN touches any
 * tenant-scoped model.
 */
class LessonImportPdf extends Command
{
    protected $signature = 'lesson:import
        {tenant : tenant slug}
        {pdf : absolute path to the PDF}
        {--book= : book title (defaults to the file name)}
        {--chapter= : chapter title}
        {--grade= : grade this book is for (blank = every grade)}
        {--title= : title of the first lesson created (defaults to the book title)}
        {--sync : run the extraction in this process instead of queueing}
        {--build : also create the first draft lesson from the extracted pages}';

    protected $description = 'Import a book PDF into the lesson library (extract pages, figures and text).';

    public function handle(): int
    {
        $tenant = Tenant::query()->where('slug', $this->argument('tenant'))->first();

        if (! $tenant) {
            $this->error('Unknown tenant slug.');

            return self::FAILURE;
        }

        app()->instance('tenant', $tenant);

        $pdf = (string) $this->argument('pdf');

        if (! is_file($pdf)) {
            $this->error('PDF not found.');

            return self::FAILURE;
        }

        $bookTitle = (string) ($this->option('book') ?: pathinfo($pdf, PATHINFO_FILENAME));

        $folder = 'lessons/pdf';
        $target = TenantUploads::pathFor($folder, Str::random(24).'.pdf');

        if (! @copy($pdf, $target)) {
            $this->error('Unable to copy the PDF into the tenant tree.');

            return self::FAILURE;
        }

        $import = LessonImport::create([
            'book_title' => $bookTitle,
            'grade' => $this->option('grade') ?: null,
            'pdf_path' => TenantUploads::relative($target, $folder),
            'status' => 'queued',
            'created_by_user_id' => User::query()->where('role', User::ROLE_TENANT_ADMIN)->value('id'),
        ]);

        $this->info("Import #{$import->id} created for tenant {$tenant->slug}.");

        if ($this->option('sync')) {
            dispatch_sync(ExtractLessonPdf::for($import));
        } else {
            ExtractLessonPdf::dispatch($import->tenant_id, $import->id);
        }

        $import->refresh();
        $this->info("Status: {$import->status}".($import->error_code ? " ({$import->error_code})" : ''));

        if ($import->status === 'failed') {
            return self::FAILURE;
        }

        if ($this->option('build')) {
            $lesson = app(\App\Support\LessonBuilder::class)->build(
                $import,
                (string) ($this->option('title') ?: $bookTitle),
                $this->option('chapter') ?: null,
                (int) ($this->option('grade') ?: null) ?: null,
            );

            $this->info("Lesson #{$lesson->id} created with {$lesson->blocks()->count()} blocks.");
        }

        return self::SUCCESS;
    }
}
