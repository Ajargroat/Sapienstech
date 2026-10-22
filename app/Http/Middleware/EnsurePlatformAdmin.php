<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The operations console is OURS, not a tenant's.
 *
 * The PDF pipeline is our internal machinery: a book is uploaded once, its
 * text is extracted once, and only the resulting lesson ever reaches a tenant.
 * So it must not hang off a tenant panel where a consultant's role matrix,
 * feature flags and shell decide whether it renders — and it must not depend
 * on a queue worker someone remembered to start.
 *
 * Only a platform admin (`users.role = platform_admin`) may enter. Anything
 * else is a 404, matching every other gated surface in this app: a 403 would
 * confirm that the console exists.
 *
 * Note the model check: a STUDENT session also travels through `web`, so a
 * truthiness test here would let a student into the extraction console.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->canRunOps(), 404);

        return $next($request);
    }
}
