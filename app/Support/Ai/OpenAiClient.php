<?php

namespace App\Support\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one and only reader of the provider credentials.
 *
 * Speaks the OpenAI /chat/completions shape, which OpenAI, Groq, OpenRouter,
 * DeepSeek, Together and a local Ollama/vLLM all accept — so "change provider"
 * is an AI_BASE_URL edit, not a code change.
 *
 * Three rules this class exists to enforce:
 *
 *  1. The key never leaves the server. It is read from config here and appears
 *     in no response, no exception message the UI sees, and no log line. The
 *     provider's error *body* is treated as hostile for the same reason — some
 *     gateways echo the request (and its Authorization header) back on a
 *     malformed call — so it is truncated into the log and never returned.
 *
 *  2. Failure is data, not an exception. Every path returns AiResult, so the
 *     caller renders a named state instead of a 500. A provider outage is an
 *     expected condition of an optional feature.
 *
 *  3. Every call is bounded. Both a connect timeout and a total timeout are
 *     set, because a hanging upstream would otherwise hold a PHP worker.
 */
class OpenAiClient
{
    /**
     * A single chat completion, with model failover.
     *
     * The model list is tried in order; the first model that answers
     * successfully wins. Failover happens only for *transient* failures —
     * a 400 (bad model name, malformed request) is a configuration defect that
     * every model would reject, so retrying it just multiplies the latency of a
     * guaranteed failure.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<string>  $models  primary first, then fallbacks
     */
    public function chat(array $messages, array $models, ?float $temperature = null, ?int $maxTokens = null): AiResult
    {
        $keys = $this->keys();

        if ($keys === []) {
            return AiResult::failure(AiError::Unconfigured, $models[0] ?? 'none', 0);
        }

        if ($models === []) {
            return AiResult::failure(AiError::BadRequest, 'none', 0, 'no model configured');
        }

        $last = null;

        foreach ($models as $model) {
            $last = $this->attempt($keys, $messages, $model, $temperature, $maxTokens);

            if ($last->ok) {
                return $last;
            }

            // Only transient problems are worth another model.
            if (! in_array($last->error, [AiError::RateLimited, AiError::Unreachable, AiError::ServerError], true)) {
                return $last;
            }

            Log::warning('ai: model failed, trying next', [
                'model' => $model,
                'code' => $last->error?->value,
            ]);
        }

        return $last;
    }

    /**
     * A minimal probe that proves the *credential and endpoint* work, used by
     * AiHealth. It asks the provider to list its models, which costs nothing
     * and does not consume tokens — a probe that spends money on every cache
     * expiry would be its own small leak.
     *
     * @return array{ok: bool, error: ?AiError}
     */
    public function probe(): array
    {
        $keys = $this->keys();

        if ($keys === []) {
            return ['ok' => false, 'error' => AiError::Unconfigured];
        }

        try {
            $response = Http::withToken($keys[0])
                ->timeout($this->connectTimeout())
                ->connectTimeout($this->connectTimeout())
                ->acceptJson()
                ->get($this->endpoint('models'));

            if ($response->successful()) {
                return ['ok' => true, 'error' => null];
            }

            return ['ok' => false, 'error' => $this->classify($response)];
        } catch (Throwable $e) {
            Log::warning('ai: health probe failed: '.$e->getMessage());

            return ['ok' => false, 'error' => AiError::Unreachable];
        }
    }

    /** True when at least one credential is configured for this deployment. */
    public function configured(): bool
    {
        return $this->keys() !== [];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /** @param list<string> $keys */
    private function attempt(array $keys, array $messages, string $model, ?float $temperature, ?int $maxTokens): AiResult
    {
        $started = (int) round(microtime(true) * 1000);

        $payload = array_filter([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            // Ask for strict JSON so the reply can be parsed as suggestions
            // rather than scraped. Not every compatible provider honours it,
            // which is exactly why parseReply() still validates every field.
            'response_format' => ['type' => 'json_object'],
        ], fn ($v) => $v !== null);

        try {
            $response = Http::withToken($keys[0])
                ->timeout($this->timeout())
                ->connectTimeout($this->connectTimeout())
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint('chat/completions'), $payload);
        } catch (ConnectionException $e) {
            return AiResult::fromThrowable($e, $model, $this->elapsed($started));
        } catch (Throwable $e) {
            return AiResult::fromThrowable($e, $model, $this->elapsed($started));
        }

        $latency = $this->elapsed($started);

        if (! $response->successful()) {
            $error = $this->classify($response);

            Log::warning('ai: completion failed', [
                'code' => $error->value,
                'status' => $response->status(),
                'model' => $model,
            ]);

            return AiResult::failure($error, $model, $latency, $response->body());
        }

        $text = (string) data_get($response->json(), 'choices.0.message.content', '');

        if (trim($text) === '') {
            return AiResult::failure(AiError::BadResponse, $model, $latency, 'empty content');
        }

        return AiResult::success($text, $model, [
            'input_tokens' => (int) data_get($response->json(), 'usage.prompt_tokens', 0),
            'output_tokens' => (int) data_get($response->json(), 'usage.completion_tokens', 0),
        ], $latency);
    }

    /**
     * Map an HTTP status onto the closed error set.
     *
     * 401/403 become BadRequest rather than a distinct "auth" case on purpose:
     * both mean "this deployment's credential or model access is wrong", and
     * telling the *consultant* which of the two it is would leak configuration
     * detail to someone who cannot fix it. The operator gets the detail from
     * the log.
     */
    private function classify(Response $response): AiError
    {
        return match (true) {
            $response->status() === 429 => AiError::RateLimited,
            $response->status() >= 500 => AiError::ServerError,
            $response->status() >= 400 => AiError::BadRequest,
            default => AiError::BadResponse,
        };
    }

    /** @return list<string> */
    private function keys(): array
    {
        $keys = config('services.ai.keys', []);

        if (is_string($keys)) {
            $keys = explode(',', $keys);
        }

        return array_values(array_filter(array_map('trim', (array) $keys)));
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.ai.base_url', 'https://api.openai.com/v1'), '/').'/'.ltrim($path, '/');
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.ai.timeout', 30));
    }

    private function connectTimeout(): int
    {
        return max(1, (int) config('services.ai.connect_timeout', 8));
    }

    private function elapsed(int $started): int
    {
        return (int) round(microtime(true) * 1000) - $started;
    }
}
