<?php

namespace App\Support\Ai;

/**
 * Every way an assistant turn can fail, as a closed set.
 *
 * The point of an enum rather than a bare string is that the UI copy and the
 * stored `error_code` come from the same source: the controller renders
 * `config('theme.ai.<case>_text')` for the case, so a new failure mode cannot
 * be added without deciding what the consultant is told about it. It also keeps
 * provider response bodies — which can quote the API key back on a malformed
 * request — from ever reaching the browser, because the code is all that
 * crosses that boundary.
 */
enum AiError: string
{
    /** No API key configured for this deployment. */
    case Unconfigured = 'unconfigured';

    /** Connection refused, DNS failure, timeout — we never reached the provider. */
    case Unreachable = 'unreachable';

    /** Provider answered 429, or we refused locally on the per-minute throttle. */
    case RateLimited = 'rate_limited';

    /** The tenant's monthly token ceiling is spent. */
    case BudgetExhausted = 'budget_exhausted';

    /** Provider answered 4xx (bad model name, malformed request, no access). */
    case BadRequest = 'bad_request';

    /** Provider answered 5xx. */
    case ServerError = 'server_error';

    /** We reached the provider, but its answer was not the structured shape we asked for. */
    case BadResponse = 'bad_response';

    /**
     * The i18n key on the `ai` config block holding this failure's copy.
     *
     * Kept next to the cases so adding one forces the question "what does the
     * consultant read?" rather than leaving it to a default in the view.
     */
    public function textKey(): string
    {
        return match ($this) {
            self::Unconfigured => 'unconfigured_text',
            self::Unreachable => 'unreachable_text',
            self::RateLimited => 'rate_limited_text',
            self::BudgetExhausted => 'budget_exhausted_text',
            self::BadRequest, self::ServerError, self::BadResponse => 'generic_error_text',
        };
    }

    /** HTTP status the controller should answer with. */
    public function status(): int
    {
        return match ($this) {
            self::BudgetExhausted, self::RateLimited => 429,
            self::Unconfigured => 503,
            self::Unreachable, self::ServerError => 502,
            self::BadRequest, self::BadResponse => 502,
        };
    }

    /** True when the consultant can sensibly just try again. */
    public function retryable(): bool
    {
        return match ($this) {
            self::RateLimited, self::Unreachable, self::ServerError => true,
            self::Unconfigured, self::BudgetExhausted, self::BadRequest, self::BadResponse => false,
        };
    }
}
