<?php

namespace App\Support\Ai;

use Throwable;

/**
 * The transport result of one provider call: either a completion or a typed
 * failure. Never an exception out of the client, because "the provider is down"
 * is an expected state this feature must render, not an error page.
 *
 * Raw provider output is carried for *logging only*. `errorMessage()` is
 * deliberately not exposed to the view layer — a provider's error body can
 * quote the request back, including the Authorization header on some
 * gateways, so it must never travel further than the log.
 */
final class AiResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $text,
        public readonly ?AiError $error,
        public readonly ?string $rawError,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $latencyMs,
    ) {}

    /** @param array{text?: string, input_tokens?: int, output_tokens?: int} $completion */
    public static function success(string $text, string $model, array $completion, int $latencyMs): self
    {
        return new self(
            ok: true,
            text: $text,
            error: null,
            rawError: null,
            model: $model,
            inputTokens: (int) ($completion['input_tokens'] ?? 0),
            outputTokens: (int) ($completion['output_tokens'] ?? 0),
            latencyMs: $latencyMs,
        );
    }

    public static function failure(AiError $error, string $model, int $latencyMs, ?string $raw = null): self
    {
        return new self(
            ok: false,
            text: null,
            error: $error,
            rawError: $raw,
            model: $model,
            inputTokens: 0,
            outputTokens: 0,
            latencyMs: $latencyMs,
        );
    }

    public static function fromThrowable(Throwable $e, string $model, int $latencyMs): self
    {
        return self::failure(AiError::Unreachable, $model, $latencyMs, $e->getMessage());
    }

    /**
     * A one-line summary for the log. Truncated and stripped of newlines so a
     * provider cannot dump an HTML error page into the log file.
     */
    public function logSummary(): string
    {
        if ($this->ok) {
            return "ok model={$this->model} in={$this->inputTokens} out={$this->outputTokens} ms={$this->latencyMs}";
        }

        $raw = trim((string) preg_replace('/\s+/', ' ', substr((string) $this->rawError, 0, 300)));

        return "fail code={$this->error?->value} model={$this->model} ms={$this->latencyMs}".($raw !== '' ? " raw={$raw}" : '');
    }
}
