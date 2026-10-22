<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the platform's security headers to every response.
 *
 * This is the floor that makes per-tenant bespoke landing pages defensible:
 * a tenant-authored page is same-origin code running on that tenant's real
 * domain, so the policy — not trust in the page — is what stops it from
 * loading foreign script or being framed.
 *
 * The policy is assembled from config/security.php and from nothing else. No
 * header value is ever built from request input, so there is no path from a
 * crafted Host header or query string into the policy.
 *
 * Rollout is deliberately staged: `csp.mode = report-only` evaluates the
 * policy without blocking, so an over-tight rule surfaces as report noise
 * instead of a broken tenant site. Only `enforce` blocks.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // The nonce must exist BEFORE the response body is rendered: views read
        // it back through csp_nonce() and stamp it on their inline tags. If it
        // were minted after $next(), every tag would render nonce="" and the
        // policy would authorise nothing.
        $this->mintNonce($request);

        $response = $next($request);

        foreach ((array) config('security.headers', []) as $name => $value) {
            if (is_string($value) && $value !== '' && ! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        $mode = (string) config('security.csp.mode', 'off');

        if ($mode === 'enforce' || $mode === 'report-only') {
            $header = $mode === 'enforce'
                ? 'Content-Security-Policy'
                : 'Content-Security-Policy-Report-Only';

            // A view may declare its own policy (the bespoke landing surface
            // does, so a generated page cannot widen it); never overwrite it.
            if (! $response->headers->has($header)) {
                $response->headers->set($header, $this->policy($request));
            }
        }

        return $response;
    }

    /**
     * Mint this response's nonce once and publish it on the request.
     *
     * Idempotent on purpose: handle() calls it before rendering and policy()
     * calls it again afterwards. The second call must find the first value,
     * not replace it — a nonce that changed between rendering and the header
     * would authorise no tag at all.
     */
    protected function mintNonce(Request $request): ?string
    {
        if (empty(config('security.csp.nonce'))) {
            return null;
        }

        $existing = $request->attributes->get('csp_nonce');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        return $nonce;
    }

    /** Build the policy string for this request. */
    public function policy(Request $request): string
    {
        $csp = (array) config('security.csp', []);

        $scriptSrc = array_merge(
            (array) ($csp['script_src'] ?? ["'self'"]),
            (array) ($csp['script_src_extra'] ?? []),
            (array) ($csp['inline_script_hashes'] ?? []),
        );

        // The same nonce the pages already stamped onto their inline tags.
        if (($nonce = $this->mintNonce($request)) !== null) {
            $scriptSrc[] = "'nonce-{$nonce}'";
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $scriptSrc,
            'style-src' => (array) ($csp['style_src'] ?? ["'self'"]),
            'font-src' => (array) ($csp['font_src'] ?? []),
            'img-src' => (array) ($csp['img_src'] ?? []),
            'connect-src' => (array) ($csp['connect_src'] ?? []),
            // A landing page may embed a video; frames of our own pages are
            // not allowed to be nested anywhere (see frame-ancestors below).
            'media-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'none'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        $parts = [];

        foreach ($directives as $name => $values) {
            $values = array_values(array_unique(array_filter($values, static fn ($v) => $v !== null && $v !== '')));

            if ($values !== []) {
                $parts[] = $name.' '.implode(' ', $values);
            }
        }

        $report = trim((string) ($csp['report_uri'] ?? ''));

        if ($report !== '') {
            $parts[] = 'report-uri '.$report;
        }

        return implode('; ', $parts);
    }
}
