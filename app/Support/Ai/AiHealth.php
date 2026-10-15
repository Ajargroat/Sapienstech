<?php

namespace App\Support\Ai;

use Illuminate\Support\Facades\Cache;

/**
 * "Is the assistant usable right now?" — answered cheaply and cached.
 *
 * The panel shows a status dot and the controller consults this before building
 * a request, so the answer has to be available without a provider round-trip on
 * every page load. A probe is therefore run at most once per
 * `services.ai.health_ttl` seconds and the verdict is cached.
 *
 * The cache key is tenant-scoped even though the credentials are not: whether
 * the feature is *enabled* is a tenant setting, so one tenant switching the
 * assistant off must not be masked by another tenant's successful probe.
 *
 * A stale-ok verdict is never trusted for a *write* — the request path calls
 * the client anyway — so this stays an advisory signal for the UI, not a gate.
 */
class AiHealth
{
    public const OK = 'ok';

    public const UNCONFIGURED = 'unconfigured';

    public const DOWN = 'down';

    public const DISABLED = 'disabled';

    public function __construct(private readonly OpenAiClient $client) {}

    /**
     * @return array{status: string, ok: bool, checked_at: string}
     */
    public function check(bool $fresh = false): array
    {
        $tenantId = tenant()?->id;
        $key = "ai:health:{$tenantId}";

        if (! $fresh) {
            $cached = Cache::get($key);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $verdict = $this->probe();
        Cache::put($key, $verdict, $this->ttl());

        return $verdict;
    }

    /**
     * Force a probe and cache the result. Used by the explicit health endpoint,
     * which is the consultant's "try again" affordance — it must never be served
     * a cached failure when the operator has just fixed the key.
     */
    public function refresh(): array
    {
        Cache::forget('ai:health:'.(tenant()?->id));

        return $this->check(true);
    }

    /** @return array{status: string, ok: bool, checked_at: string} */
    private function probe(): array
    {
        if (! (bool) site('ai.enabled', false)) {
            return $this->verdict(self::DISABLED, false);
        }

        if (! $this->client->configured()) {
            return $this->verdict(self::UNCONFIGURED, false);
        }

        $result = $this->client->probe();

        return $result['ok']
            ? $this->verdict(self::OK, true)
            : $this->verdict(self::DOWN, false);
    }

    /** @return array{status: string, ok: bool, checked_at: string} */
    private function verdict(string $status, bool $ok): array
    {
        return [
            'status' => $status,
            'ok' => $ok,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function ttl(): int
    {
        return max(10, (int) config('services.ai.health_ttl', 60));
    }
}
