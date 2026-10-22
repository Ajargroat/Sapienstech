<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The security floor every tenant response inherits.
 *
 * These are invariant tests, not output snapshots: they assert the properties
 * that make per-tenant bespoke pages defensible (no inline script execution,
 * no framing, no foreign script origins) rather than an exact policy string,
 * so adding a directive does not force an unrelated failure here.
 */
class SecurityHeadersTest extends TestCase
{
    private function policy(): string
    {
        config()->set('security.csp.nonce', false);

        return (new SecurityHeaders)->policy(Request::create('https://example.test/'));
    }

    private function dispatch(Request $request): Response
    {
        return (new SecurityHeaders)->handle($request, static function (): Response {
            return new Response('<html></html>');
        });
    }

    public function test_the_platform_sends_hardening_headers(): void
    {
        $response = $this->dispatch(Request::create('https://example.test/'));

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));
    }

    public function test_the_policy_forbids_inline_script_and_foreign_objects(): void
    {
        $policy = $this->policy();

        // The single most important line in the policy: with 'unsafe-inline'
        // here, every claim in the security model collapses.
        $scriptSrc = $this->directive($policy, 'script-src');
        $this->assertNotContains("'unsafe-inline'", $scriptSrc);
        $this->assertNotContains("'unsafe-eval'", $scriptSrc);

        $this->assertSame(["'none'"], $this->directive($policy, 'object-src'));
        $this->assertSame(["'none'"], $this->directive($policy, 'base-uri'));
        $this->assertSame(["'self'"], $this->directive($policy, 'frame-ancestors'));
        $this->assertSame(["'self'"], $this->directive($policy, 'form-action'));
    }

    public function test_script_origins_come_only_from_configuration(): void
    {
        config()->set('security.csp.script_src_extra', []);
        config()->set('security.csp.inline_script_hashes', []);

        $scriptSrc = $this->directive($this->policy(), 'script-src');

        // Nothing but same-origin unless the deployed config says otherwise.
        $this->assertSame(["'self'"], $scriptSrc);
    }

    public function test_report_only_is_the_default_and_enforce_is_opt_in(): void
    {
        // Report-only never blocks, which is what makes a first rollout safe.
        $this->assertSame('report-only', config('security.csp.mode'));

        config()->set('security.csp.mode', 'report-only');
        $response = $this->dispatch(Request::create('https://example.test/'));

        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));

        config()->set('security.csp.mode', 'enforce');
        $response = $this->dispatch(Request::create('https://example.test/'));

        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_off_sends_no_policy_at_all(): void
    {
        config()->set('security.csp.mode', 'off');

        $response = $this->dispatch(Request::create('https://example.test/'));

        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));

        // The other headers are unconditional â€” they are cheap and never break
        // a page, so turning the policy off must not turn the floor off.
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_a_view_may_declare_its_own_policy_and_is_never_overwritten(): void
    {
        config()->set('security.csp.mode', 'enforce');

        $response = (new SecurityHeaders)->handle(
            Request::create('https://example.test/'),
            static function (): Response {
                $response = new Response('<html></html>');
                $response->headers->set('Content-Security-Policy', "default-src 'none'");

                return $response;
            },
        );

        $this->assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_a_nonce_is_published_only_when_enabled(): void
    {
        config()->set('security.csp.nonce', false);
        $request = Request::create('https://example.test/');
        (new SecurityHeaders)->handle($request, static fn (): Response => new Response);
        $this->assertNull($request->attributes->get('csp_nonce'));

        config()->set('security.csp.nonce', true);
        $request = Request::create('https://example.test/');
        $response = (new SecurityHeaders)->handle($request, static fn (): Response => new Response);

        $nonce = $request->attributes->get('csp_nonce');
        $this->assertIsString($nonce);
        $this->assertNotSame('', $nonce);
        $this->assertStringContainsString("'nonce-{$nonce}'", (string) $response->headers->get('Content-Security-Policy-Report-Only'));
    }

    /** @return list<string> the values of one directive, without its name */
    private function directive(string $policy, string $name): array
    {
        foreach (explode(';', $policy) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $chunks = preg_split('/\s+/', $part, 2);
            if ($chunks[0] === $name) {
                return $chunks[1] === '' ? [] : preg_split('/\s+/', $chunks[1]);
            }
        }

        return [];
    }
}
