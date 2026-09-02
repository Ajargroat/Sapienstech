<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * If an authenticated consultant/admin user is on a domain they are not
 * allowed to use, end the session and send them back to the login page.
 *
 * Note: We explicitly check for the User model. Students authenticate via
 * a separate guard and don't have domain-pinning rules applied yet, so
 * we bypass this check for them to avoid method-not-found errors.
 */
class EnsureUserDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only enforce domain pinning for consultant/admin users, not students.
        if ($user instanceof User) {
            $currentDomain = domain();

            if ($currentDomain && ! $user->canLoginThrough($currentDomain)) {
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->withErrors(['email' => 'حساب شما اجازه ورود از این دامنه را ندارد.']);
            }
        }

        return $next($request);
    }
}
