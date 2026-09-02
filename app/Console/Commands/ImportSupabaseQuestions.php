<?php

namespace App\Console\Commands;

use App\Models\Answer;
use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Copies questions from the Supabase question bank into the local
 * questions/answers tables. Options are stored as one (double-encoded)
 * JSON column and are exploded into answer rows. Diagrams are kept as a
 * bbox over the full page image: each page is downloaded once per source
 * into storage/app/public/pages/, and the question stores the crop rect +
 * page size as JSON so the browser frames the diagram with pure CSS.
 *
 * Note: questions.diagram_url is broken in the source data (it points at a
 * non-existent "unknown" object), so the page image is resolved through the
 * question's source row (sources.storage_url) instead.
 */
class ImportSupabaseQuestions extends Command
{
    protected $signature = 'exam:import-supabase
                            {--limit=30 : Number of questions to import}
                            {--tenant=1 : Local tenant_id to stamp on imported rows}
                            {--bbox-mode=auto : auto | corners | xywh}
                            {--diagrams-only : Only fetch questions that have a diagram bbox}
                            {--dry-run : Fetch everything and report, write nothing}';

    protected $description = 'Import questions (page image + bbox for browser-side cropping) from Supabase';

    private string $api;
    private string $key;
    private string $bucket;

    /** source_id => ['path' => ?string, 'size' => ?array] */
    private array $pages = [];

    public function handle(): int
    {
        $this->api = rtrim((string) config('services.supabase.url'), '/');
        $this->key = (string) config('services.supabase.service_role_key');
        $this->bucket = (string) config('services.supabase.bucket', 'konkour-pages');

        if ($this->api === '' || $this->key === '') {
            $this->error('Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY in .env first.');

            return self::FAILURE;
        }

        $tenantId = (int) $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');

        $params = [
            'select' => 'id,source_id,question_number,question_text,options,difficulty,'
                .'has_diagram,diagram_url,diagram_bbox,correct_option_label,correct_option_text',
            'order' => 'created_at.asc',
            'limit' => (int) $this->option('limit'),
        ];

        if ($this->option('diagrams-only')) {
            $params['diagram_bbox'] = 'not.is.null';
        }

        $rows = $this->request('/rest/v1/questions', $params);

        if ($rows === null) {
            $this->error('Could not read /rest/v1/questions — check the key and project URL.');

            return self::FAILURE;
        }

        $imported = $skipped = 0;

        foreach ($rows as $row) {
            if (! $dryRun && Question::where('supabase_id', $row['id'])->exists()) {
                $skipped++;

                continue;
            }

            $page = $this->pageFor($row);
            $bbox = $this->bboxJson((string) ($row['diagram_bbox'] ?? ''), $page['size']);

            if ($dryRun) {
                $this->line(sprintf(
                    '#%s | %s | page=%s | bbox=%s -> %s',
                    substr((string) $row['id'], 0, 8),
                    mb_substr((string) $row['question_text'], 0, 40),
                    $page['path'] ?? 'none/failed',
                    (string) ($row['diagram_bbox'] ?? '-'),
                    $bbox ? json_encode($bbox) : 'invalid',
                ));

                continue;
            }

            $question = Question::create([
                'tenant_id' => $tenantId,
                'supabase_id' => $row['id'],
                'chapter_id' => null,
                'question_text' => (string) $row['question_text'],
                'question_image_path' => $page['path'],
                'question_image_bbox' => $page['path'] ? $bbox : null,
                'solution_text' => $this->explanationFor((string) $row['id']),
                'question_number_in_book' => isset($row['question_number'])
                    ? (string) $row['question_number'] : null,
                'difficulty' => $this->mapDifficulty($row['difficulty'] ?? null),
                'question_type' => 'multiple_choice',
            ]);

            foreach ($this->parseOptions($row) as $opt) {
                Answer::create([
                    'tenant_id' => $tenantId,
                    'question_id' => $question->id,
                    'answer_text' => $opt['text'],
                    'is_correct' => $opt['correct'],
                ]);
            }

            $imported++;
        }

        $this->info($dryRun
            ? 'Dry run: '.count($rows).' rows inspected.'
            : "Imported: $imported | skipped (already present): $skipped");

        return self::SUCCESS;
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
     * {"pw","ph"} page size, all in page pixels.
     */
    private function bboxJson(string $bboxRaw, ?array $pageSize): ?array
    {
        $box = json_decode($bboxRaw, true);

        if (! is_array($box)) {
            return null;
        }

        $mode = (string) $this->option('bbox-mode');

        if (array_is_list($box) && count($box) === 4) {
            [$a, $b, $c, $d] = array_map('floatval', $box);

            $asCorners = $mode === 'corners'
                || ($mode === 'auto' && $c > $a && $d > $b);

            if ($asCorners) {          // [x1, y1, x2, y2]
                [$x, $y, $w, $h] = [$a, $b, $c - $a, $d - $b];
            } else {                    // [x, y, w, h]
                [$x, $y, $w, $h] = [$a, $b, $c, $d];
            }
        } else {
            $x = (float) ($box['x'] ?? $box['left'] ?? 0);
            $y = (float) ($box['y'] ?? $box['top'] ?? 0);
            $w = (float) ($box['width'] ?? $box['w'] ?? (isset($box['right']) ? $box['right'] - $x : 0));
            $h = (float) ($box['height'] ?? $box['h'] ?? (isset($box['bottom']) ? $box['bottom'] - $y : 0));
        }

        if ($w <= 0 || $h <= 0) {
            return null;
        }

        return [
            'x' => (int) round($x),
            'y' => (int) round($y),
            'w' => (int) round($w),
            'h' => (int) round($h),
            'pw' => $pageSize[0] ?? null,
            'ph' => $pageSize[1] ?? null,
        ];
    }

    private function download(string $ref): ?string
    {
        if (str_starts_with($ref, 'http')) {
            $res = Http::timeout(120)->get($ref);

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
        $sign = Http::withHeaders($this->authHeaders())
            ->timeout(30)
            ->post("{$this->api}/storage/v1/object/sign/{$this->bucket}/".ltrim($ref, '/'), [
                'expiresIn' => 3600,
            ]);

        if (! $sign->successful() || ! ($url = $sign->json('signedURL'))) {
            return null;
        }

        $res = Http::timeout(120)->get(str_starts_with($url, 'http') ? $url : $this->api.$url);

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
        $res = Http::withHeaders($this->authHeaders())
            ->timeout(30)
            ->get($this->api.$path, $params);

        return $res->successful() ? $res->json() : null;
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
