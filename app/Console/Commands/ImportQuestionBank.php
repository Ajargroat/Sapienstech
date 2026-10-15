<?php

namespace App\Console\Commands;

use App\Models\Answer;
use App\Models\Question;
use App\Support\BboxParser;
use App\Support\QuestionBankMeta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Fills the local shared question bank from the Supabase question bank
 * (PostgreSQL) — the importer half of the pool described in
 * App\Models\Concerns\SharedAcrossTenants.
 *
 * Three source tables are read: `questions`, `answers` (only for the
 * explanation) and `sources` (the page image a question was OCR'd from).
 * Options arrive as one double-encoded JSON column and are exploded into
 * answer rows; the diagram is kept as a bbox over the full page image so the
 * browser frames it with pure CSS (see components/cropped-image.blade.php).
 *
 * Rows carry no tenant_id — they are pooled. Re-runs are idempotent through
 * the per-row supabase_id (unique), and each row's answers are rebuilt from
 * scratch since they have no stable source key. Only consultant-relevant
 * data is selected; an approved/disapproved flag on the source is not read.
 *
 * Note: questions.diagram_url is broken in the source data (it points at a
 * non-existent "unknown" object), so the page image is resolved through the
 * question's source row (sources.storage_url) instead.
 */
class ImportQuestionBank extends Command
{
    protected $signature = 'bank:import
                            {--limit=500 : Max questions to pull (0 = all rows)}
                            {--bbox-mode=yxxy : yxxy (source frame) | corners | xywh | auto}
                            {--dry-run : Fetch everything and report, write nothing}';

    protected $description = 'Import the shared question bank (page image + bbox for browser-side cropping) from Supabase';

    private string $api;

    private string $key;

    private string $bucket;

    private bool $verify = true;

    /** source_id => ['path' => ?string, 'size' => ?array] */
    private array $pages = [];

    public function handle(): int
    {
        $this->api = rtrim((string) config('services.supabase.url'), '/');
        $this->key = (string) config('services.supabase.service_role_key');
        $this->bucket = (string) config('services.supabase.bucket', 'konkour-pages');
        $this->verify = (bool) config('services.supabase.verify', true);

        if ($this->api === '' || $this->key === '') {
            $this->error('Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY in .env first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $rows = $this->fetchQuestions((int) $this->option('limit'));

        if ($rows === null) {
            $this->error('Could not read /rest/v1/questions — check the key and project URL.');

            return self::FAILURE;
        }

        $created = $updated = 0;

        foreach ($rows as $row) {
            $page = $this->pageFor($row);
            $bbox = $this->bboxJson((string) ($row['diagram_bbox'] ?? ''), $page['size']);

            if ($dryRun) {
                $this->line(sprintf(
                    '#%s | %s | page=%s | bbox=%s -> %s',
                    substr((string) $row['id'], 0, 8),
                    mb_substr((string) $row['question_text'], 0, 40),
                    $page['path'] ?? 'none/failed',
                    (string) ($row['diagram_bbox'] ?? '-'),
                    $bbox ? json_encode($bbox)
                        : (($row['diagram_bbox'] ?? '') === '' ? 'no bbox' : 'unparsed'),
                ));

                continue;
            }

            $question = Question::updateOrCreate(
                ['supabase_id' => $row['id']],
                [
                    'chapter_id' => null,
                    'question_text' => (string) $row['question_text'],
                    // Provenance: the source page is kept even without a box
                    // (which page the question came from is worth knowing).
                    'question_image_path' => $page['path'],
                    // The crop only exists when a box was parsed against a
                    // page we actually hold — a path never implies a picture.
                    'question_image_bbox' => $page['path'] ? $bbox : null,
                    'solution_text' => $this->explanationFor((string) $row['id']),
                    'question_number_in_book' => isset($row['question_number'])
                        ? (string) $row['question_number'] : null,
                    'difficulty' => $this->mapDifficulty($row['difficulty'] ?? null),
                    'question_type' => 'multiple_choice',
                    // Normalized so the picker's lesson/corp/chapter filters and
                    // tags work off canonical strings (see QuestionBankMeta).
                    'subject' => QuestionBankMeta::subject($row['subject'] ?? null),
                    'corp' => QuestionBankMeta::corp($row['corp'] ?? null),
                    'chapter_label' => QuestionBankMeta::chapterLabel(
                        $row['topic'] ?? null, $row['grade'] ?? null
                    ),
                ]
            );

            $question->wasRecentlyCreated ? $created++ : $updated++;

            // Answers have no stable source key, so they are rebuilt wholesale.
            $question->answers()->delete();

            foreach ($this->parseOptions($row) as $opt) {
                Answer::create([
                    'question_id' => $question->id,
                    'answer_text' => $opt['text'],
                    'is_correct' => $opt['correct'],
                ]);
            }
        }

        $this->info($dryRun
            ? 'Dry run: '.count($rows).' rows inspected.'
            : "Imported: $created new | $updated refreshed | total ".count($rows).' rows.');

        return self::SUCCESS;
    }

    /**
     * Read the whole table in pages. PostgREST caps a single request at 1000
     * rows (verified: limit=3000 still returns 1000), so one call silently
     * truncated the bank at 500 and left only one subject behind. The order
     * carries a tie-break on id so offset paging stays stable; $limit is the
     * operator's cap and 0 means "until the table is exhausted".
     *
     * @return array<int, array>|null null when the first page cannot be read
     */
    private function fetchQuestions(int $limit): ?array
    {
        $pageSize = 1000;
        $rows = [];
        $offset = 0;

        do {
            $batch = $this->request('/rest/v1/questions', [
                'select' => 'id,source_id,question_number,question_text,options,difficulty,'
                    .'has_diagram,diagram_url,diagram_bbox,correct_option_label,correct_option_text,'
                    .'subject,corp,topic,grade',
                'order' => 'created_at.asc,id.asc',
                'limit' => $pageSize,
                'offset' => $offset,
            ]);

            if ($batch === null) {
                // Fail loudly rather than import a half-read bank.
                return null;
            }

            $rows = array_merge($rows, $batch);
            $offset += count($batch);
        } while (count($batch) === $pageSize && ($limit === 0 || count($rows) < $limit));

        return $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
    }

    /**
     * The full page image for a question, downloaded at most once per
     * source and stored under storage/app/public/pages/.
     */
    private function pageFor(array $row): array
    {
        $sourceId = (string) ($row['source_id'] ?? '');

        if ($sourceId !== '' && isset($this->pages[$sourceId])) {
            return $this->pages[$sourceId];
        }

        $result = ['path' => null, 'size' => null];
        $ref = $this->pageImageRef($row);
        $bytes = $ref ? $this->download($ref) : null;

        if ($ref && $bytes === null) {
            $this->warn("  page download failed for question {$row['id']}: {$ref}");
        }

        if ($bytes !== null) {
            $tmp = tempnam(sys_get_temp_dir(), 'sup');
            file_put_contents($tmp, $bytes);

            // header parsing only -- works without the GD extension
            $size = @getimagesize($tmp);
            @unlink($tmp);

            if ($size === false) {
                $this->warn("  unreadable page image for question {$row['id']}");
            } else {
                $name = preg_replace('/[^A-Za-z0-9_-]/', '', $sourceId)
                    ?: substr((string) $row['id'], 0, 8);
                $path = 'pages/'.$name.'.jpg';

                if (! $this->option('dry-run')) {
                    Storage::disk('public')->put($path, $bytes);
                }

                $result = ['path' => $path, 'size' => [$size[0], $size[1]]];
            }
        }

        if ($sourceId !== '') {
            $this->pages[$sourceId] = $result;
        }

        return $result;
    }

    /**
     * questions.diagram_url is broken in the source data (it points at a
     * non-existent "unknown" object); the real page image lives on the
     * question's source row.
     */
    private function pageImageRef(array $row): ?string
    {
        $url = (string) ($row['diagram_url'] ?? '');

        if ($url !== '' && ! str_ends_with($url, '/unknown')) {
            return $url;
        }

        if (! empty($row['source_id'])) {
            return $this->sourceStorageUrl((string) $row['source_id']);
        }

        return $url !== '' ? $url : null;
    }

    private function sourceStorageUrl(string $sourceId): ?string
    {
        static $cache = [];

        if (array_key_exists($sourceId, $cache)) {
            return $cache[$sourceId];
        }

        $rows = $this->request('/rest/v1/sources', [
            'select' => 'storage_url',
            'id' => 'eq.'.$sourceId,
            'limit' => 1,
        ]);

        return $cache[$sourceId] = $rows[0]['storage_url'] ?? null;
    }

    /**
     * Normalize the raw bbox into {"x","y","w","h"} crop rect plus
     * {"pw","ph"} page size, all in page pixels — the parse itself lives in
     * App\Support\BboxParser so it can be unit-tested without the network.
     */
    private function bboxJson(string $bboxRaw, ?array $pageSize): ?array
    {
        return BboxParser::parse($bboxRaw, $pageSize, (string) $this->option('bbox-mode'));
    }

    private function download(string $ref): ?string
    {
        if (str_starts_with($ref, 'http')) {
            $res = $this->http()->timeout(120)->get($ref);

            if ($res->successful()) {
                return $res->body();
            }

            // possibly an expired signed URL -> re-sign from the object path
            $path = (string) parse_url($ref, PHP_URL_PATH);

            if (preg_match('#/object/(?:public|authenticated|sign)/[^/]+/(.+)$#', $path, $m)) {
                return $this->download(urldecode($m[1]));
            }

            return null;
        }

        // plain bucket path on a private bucket -> exchange for a 1h signed URL
        $sign = $this->http()
            ->timeout(30)
            ->post("{$this->api}/storage/v1/object/sign/{$this->bucket}/".ltrim($ref, '/'), [
                'expiresIn' => 3600,
            ]);

        if (! $sign->successful() || ! ($url = $sign->json('signedURL'))) {
            return null;
        }

        $res = $this->http()->timeout(120)->get(str_starts_with($url, 'http') ? $url : $this->api.$url);

        return $res->successful() ? $res->body() : null;
    }

    private function parseOptions(array $row): array
    {
        $decoded = json_decode((string) ($row['options'] ?? '[]'), true);

        // The pipeline double-encoded the array into the text column, so one
        // json_decode yields another JSON string that needs decoding again.
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $label = $row['correct_option_label'] ?? null;
        $correctText = $row['correct_option_text'] ?? null;
        $out = [];

        foreach (array_values($decoded) as $i => $opt) {
            $optLabel = is_array($opt)
                ? (string) ($opt['label'] ?? $opt['key'] ?? chr(65 + $i))
                : (string) ($i + 1);
            $optText = is_array($opt)
                ? (string) ($opt['text'] ?? $opt['value'] ?? json_encode($opt, JSON_UNESCAPED_UNICODE))
                : (string) $opt;

            $out[] = [
                'text' => $optText,
                'correct' => ($label && $optLabel === $label)
                    || ($correctText && trim($optText) === trim((string) $correctText)),
            ];
        }

        return $out;
    }

    private function explanationFor(string $questionId): ?string
    {
        $rows = $this->request('/rest/v1/answers', [
            'select' => 'answer_explanation',
            'question_id' => 'eq.'.$questionId,
            'limit' => 1,
        ]);

        return $rows[0]['answer_explanation'] ?? null;
    }

    private function mapDifficulty(?string $value): ?string
    {
        return match (mb_strtolower(trim((string) $value))) {
            '' => null,
            'easy', 'آسان' => 'Easy',
            'hard', 'difficult', 'سخت', 'دشوار' => 'Hard',
            default => 'Medium',
        };
    }

    private function request(string $path, array $params): ?array
    {
        $res = $this->http()->timeout(30)->get($this->api.$path, $params);

        return $res->successful() ? $res->json() : null;
    }

    /**
     * One HTTP client for every Supabase call: auth headers plus the
     * deployment's TLS-verify choice (config services.supabase.verify), so a
     * host with no CA bundle can opt out of the "cURL error 60" failure that
     * silently emptied the imported bank of page images.
     */
    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders($this->authHeaders())
            ->withOptions(['verify' => $this->verify]);
    }

    private function authHeaders(): array
    {
        return [
            'apikey' => $this->key,
            'Authorization' => 'Bearer '.$this->key,
            'Accept' => 'application/json',
        ];
    }
}
