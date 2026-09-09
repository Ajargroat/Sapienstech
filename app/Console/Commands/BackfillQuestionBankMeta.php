<?php

namespace App\Console\Commands;

use App\Support\QuestionBankMeta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * One-time repair for questions rows imported before subject/corp/chapter
 * existed: fetches the raw metadata for each local supabase_id from the
 * Supabase bank and writes the canonical values produced by
 * App\Support\QuestionBankMeta back onto the row.
 *
 * Rows with no supabase_id (hand-made) are left alone; the remote subject
 * code is the fallback when the question row itself carries none.
 */
class BackfillQuestionBankMeta extends Command
{
    protected $signature = 'exam:backfill-bank-meta
                            {--all : Rewrite the metadata columns even where they are already filled}';

    protected $description = 'Fill questions.subject / corp / chapter_label from the Supabase bank';

    private string $api;
    private string $key;

    public function handle(): int
    {
        $this->api = rtrim((string) config('services.supabase.url'), '/');
        $this->key = (string) config('services.supabase.service_role_key');

        if ($this->api === '' || $this->key === '') {
            $this->error('Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY in .env first.');

            return self::FAILURE;
        }

        $query = DB::table('questions')->whereNotNull('supabase_id');

        if (! $this->option('all')) {
            $query->where(function ($q) {
                $q->whereNull('subject')->orWhereNull('corp');
            });
        }

        $targets = $query->pluck('supabase_id')->unique()->values();

        if ($targets->isEmpty()) {
            $this->info('Nothing to backfill.');

            return self::SUCCESS;
        }

        $updated = $missing = 0;

        foreach ($targets->chunk(80) as $chunk) {
            $rows = $this->remoteQuestions($chunk->all());

            if ($rows === null) {
                $this->error('Supabase request failed; stopping.');

                return self::FAILURE;
            }

            $byId = collect($rows)->keyBy('id');

            foreach ($chunk as $supabaseId) {
                $row = $byId->get($supabaseId);

                if (! $row) {
                    $missing++;

                    continue;
                }

                DB::table('questions')
                    ->where('supabase_id', $supabaseId)
                    ->update([
                        'subject' => QuestionBankMeta::subject(
                            $row['subject'] ?? $row['sources']['subject'] ?? null
                        ),
                        'corp' => QuestionBankMeta::corp($row['corp'] ?? null),
                        'chapter_label' => QuestionBankMeta::chapterLabel(
                            $row['topic'] ?? null, $row['grade'] ?? null
                        ),
                    ]);

                $updated++;
            }

            $this->line("  {$updated} updated so far…");
        }

        $this->info("Backfilled {$updated} questions".($missing ? " ({$missing} not found remotely)" : '').'.');

        return self::SUCCESS;
    }

    private function remoteQuestions(array $supabaseIds): ?array
    {
        $res = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => 'Bearer '.$this->key,
            'Accept' => 'application/json',
        ])
            ->timeout(45)
            ->get("{$this->api}/rest/v1/questions", [
                'select' => 'id,subject,corp,topic,grade,sources(subject)',
                'id' => 'in.('.implode(',', array_map(
                    fn ($id) => '"'.preg_replace('/[^0-9a-zA-Z_-]/', '', (string) $id).'"',
                    $supabaseIds
                )).')',
                'limit' => count($supabaseIds) + 10,
            ]);

        return $res->successful() ? $res->json() : null;
    }
}
