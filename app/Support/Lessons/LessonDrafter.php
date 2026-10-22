<?php

namespace App\Support\Lessons;

use App\Models\BookLesson;
use App\Support\Ai\AiBudget;
use App\Support\Ai\AiError;
use App\Support\Ai\AiResult;
use App\Support\Ai\OpenAiClient;

/**
 * AI drafting for a lesson, as a pure pipeline: build the prompt, call the
 * provider, validate the reply, and report a typed outcome.
 *
 * It NEVER touches an HTTP request (the controllers only enqueue) and it never
 * writes a failure artifact — `LessonDraftRunner` decides what to store. The
 * tenant MUST already be bound when `run*()` is called; the jobs do that by
 * primary key, and `AiBudget::record()` depends on it (its increments carry no
 * tenant predicate).
 */
class LessonDrafter
{
    public function __construct(
        private readonly OpenAiClient $client,
        private readonly AiBudget $budget,
    ) {}

    public static function blocksEnabled(): bool
    {
        return (bool) site('lessons.ai_blocks_enabled', false);
    }

    public static function questionsEnabled(): bool
    {
        return (bool) site('lessons.ai_questions_enabled', false);
    }

    public static function releaseEnabled(): bool
    {
        return (bool) site('lessons.questions_release_enabled', false);
    }

    /**
     * Draft the lesson's blocks.
     *
     * @return array{ok: bool, refused: bool, error: ?AiError, error_text: ?string, payload: ?array, raw: ?string, result: ?AiResult, validator: list<string>}
     */
    public function draftBlocks(BookLesson $lesson): array
    {
        $passages = LessonPrompt::passagesFor($lesson);

        if (trim($passages) === '') {
            return [
                'ok' => false, 'refused' => true, 'error' => null,
                'error_text' => 'این درس متن استخراج‌شده ندارد؛ تولید بلوک ممکن نیست.',
                'payload' => null, 'raw' => null, 'result' => null, 'validator' => ['no_text_layer'],
            ];
        }

        return $this->run(
            LessonPrompt::blocks($lesson, $passages, LessonPrompt::figuresFor($lesson)),
            fn (string $raw) => LessonBlockDraft::validate($this->extractJson($raw), $lesson),
        );
    }

    /**
     * Draft multiple-choice questions from the lesson's own passage.
     *
     * @return array{ok: bool, refused: bool, error: ?AiError, error_text: ?string, payload: ?array, raw: ?string, result: ?AiResult, validator: list<string>}
     */
    public function draftQuestions(BookLesson $lesson, ?int $count = null): array
    {
        $passages = LessonPrompt::passagesFor($lesson);

        if (trim($passages) === '') {
            return [
                'ok' => false, 'refused' => true, 'error' => null,
                'error_text' => 'این درس متن استخراج‌شده ندارد؛ تولید سؤال ممکن نیست.',
                'payload' => null, 'raw' => null, 'result' => null, 'validator' => ['no_text_layer'],
            ];
        }

        $max = max(1, (int) site('lessons.ai_questions_max', 12));
        $requested = max(3, min($max, (int) ($count ?? site('lessons.ai_questions_default', 5))));

        return $this->run(
            LessonPrompt::questions($lesson, $passages, $requested),
            function (string $raw) use ($passages, $max) {
                $decoded = $this->extractJson($raw);

                // Models do not always honour "JSON only". The configured model
                // answered with good questions as Persian markdown, and
                // `extractJson()` reading that as an empty set is what recorded
                // a usable draft as "the model returned no questions". The
                // prose reader keeps only questions grounded in the passage.
                if (! is_array($decoded['questions'] ?? null) || $decoded['questions'] === []) {
                    $fallback = LessonProse::parse($raw, $passages);

                    if (($fallback['questions'] ?? []) !== []) {
                        $decoded = $fallback;
                    }
                }

                return LessonQuestionValidator::validate($decoded, $passages, $max);
            },
        );
    }

    /**
     * @param  callable(string): array{payload: ?array, errors: list<string>}  $validate
     */
    private function run(array $messages, callable $validate): array
    {
        $outcome = [
            'ok' => false,
            'refused' => false,
            'error' => null,
            'error_text' => null,
            'payload' => null,
            'raw' => null,
            'result' => null,
            'validator' => [],
        ];

        if (! $this->budget->allows()) {
            $this->budget->recordRefusal();

            return $outcome + [
                'error' => AiError::BudgetExhausted,
                'error_text' => (string) site('ai.'.AiError::BudgetExhausted->textKey(), ''),
            ];
        }

        $result = $this->client->chat(
            $messages,
            $this->models(),
            (float) site('ai.temperature', 0.4),
            (int) site('ai.max_output_tokens', 1200),
        );

        $outcome['result'] = $result;

        if (! $result->ok) {
            return $outcome + [
                'error' => $result->error,
                'error_text' => (string) site('ai.'.($result->error?->textKey() ?? 'generic_error_text'), ''),
            ];
        }

        // The accounting step: only after a call that actually cost tokens.
        $this->budget->record($result->inputTokens, $result->outputTokens);

        $checked = $validate((string) $result->text);
        $outcome['raw'] = (string) $result->text;
        $outcome['validator'] = (array) ($checked['errors'] ?? []);

        if (($checked['payload'] ?? null) === null) {
            // The provider answered, but nothing survived validation. This is a
            // REFUSAL, not a transport failure: nothing is written to the
            // lesson (PREFERENCE §7.3 — a failed draft stores NULL).
            return $outcome + [
                'refused' => true,
                'error_text' => $outcome['validator'][0] ?? (string) site('ai.generic_error_text', ''),
            ];
        }

        return $outcome + [
            'ok' => true,
            'payload' => $checked['payload'],
        ];
    }

    /**
     * Tolerant JSON extraction: models sometimes wrap JSON in a fence or
     * prepend a sentence. Strict on the way out (the validators check fields).
     */
    public function extractJson(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(.*?)```/s', $raw, $m)) {
            $decoded = json_decode(trim($m[1]), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /** @return list<string> */
    private function models(): array
    {
        $primary = trim((string) site('ai.model', ''));
        $list = $primary !== '' ? [$primary] : [];

        foreach ((array) site('ai.fallback_models', []) as $entry) {
            $value = is_array($entry) ? ($entry['model'] ?? null) : $entry;

            if (is_string($value) && trim($value) !== '') {
                $list[] = trim($value);
            }
        }

        return array_values(array_unique($list));
    }
}
