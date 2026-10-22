<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\LandingLint;
use App\Support\LandingSurface;
use App\Support\LandingTokens;
use Illuminate\Console\Command;

/**
 * Check a bespoke landing page against the page contract.
 *
 * This is the gate an AI-generated page has to pass before it is published:
 * static problems (inline script, hard-coded colour, missing token include,
 * RTL hostiles) are exactly the class of mistake a model makes and a human
 * reviewer skims past. Non-zero exit means "do not publish".
 */
class LandingLintCommand extends Command
{
    protected $signature = 'landing:lint {slug? : Lint one tenant; omit to lint every bespoke surface}';

    protected $description = 'Validate bespoke landing pages against the token/include/security contract';

    public function handle(LandingSurface $surfaces, LandingLint $lint): int
    {
        // The core sheet is part of the contract, so a broken sheet is a
        // finding in its own right — it would make every page reference a
        // variable nothing emits.
        if ($unknown = LandingTokens::unknownPaths()) {
            $this->error('Core token sheet references paths ThemeTokens does not emit:');
            foreach ($unknown as $path) {
                $this->line('  - '.$path);
            }

            return self::FAILURE;
        }

        $targets = $this->targets($surfaces);

        if ($targets === []) {
            $this->warn('No bespoke landing surfaces found.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($targets as $dir) {
            $failed += $this->lintDirectory($dir, $surfaces, $lint) ? 0 : 1;
        }

        $this->newLine();

        if ($failed > 0) {
            $this->error("{$failed} surface(s) failed the contract.");

            return self::FAILURE;
        }

        $this->info(count($targets).' surface(s) passed the contract.');

        return self::SUCCESS;
    }

    /** @return list<string> absolute directories to lint */
    private function targets(LandingSurface $surfaces): array
    {
        $root = resource_path(LandingSurface::ROOT);

        if ($slug = $this->argument('slug')) {
            $tenant = Tenant::where('slug', $slug)->first();

            // A slug that is not a real tenant cannot own a surface: the page
            // would never be served, so linting it would be a false comfort.
            if (! $tenant) {
                $this->error("No tenant with slug \"{$slug}\".");

                return [];
            }

            $dir = $surfaces->path($tenant);

            return $dir !== null && is_dir($dir) ? [$dir] : [];
        }

        return array_values(array_filter(
            glob($root.'/*', GLOB_ONLYDIR) ?: [],
            static fn (string $dir): bool => is_file($dir.DIRECTORY_SEPARATOR.LandingSurface::VIEW),
        ));
    }

    /** @return bool true when the surface passes */
    private function lintDirectory(string $dir, LandingSurface $surfaces, LandingLint $lint): bool
    {
        $slug = basename($dir);
        $view = $dir.DIRECTORY_SEPARATOR.LandingSurface::VIEW;

        if (! is_file($view)) {
            $this->error("{$slug}: missing ".LandingSurface::VIEW);

            return false;
        }

        $source = (string) file_get_contents($view);
        $problems = $lint->check($source);

        // Publishing state is reported but never fails the lint: an
        // unpublished page that is otherwise correct is a page in review.
        $tenant = Tenant::where('slug', $slug)->first();
        $published = $tenant !== null && $surfaces->for($tenant) !== null;

        if ($problems === []) {
            $this->info(sprintf(
                '%s: OK%s',
                $slug,
                $published ? ' (published)' : ' (not published)',
            ));

            return true;
        }

        $this->error(sprintf('%s: %d problem(s)', $slug, count($problems)));

        foreach ($problems as $problem) {
            $this->line('  - '.$problem);
        }

        return false;
    }
}
