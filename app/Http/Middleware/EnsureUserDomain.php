<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
* If an authenticated user is on a domain they are not allowed to use,
* end the session and send them back to the login page.
*/
class EnsureUserDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $currentDomain = domain();

        if ($user && $currentDomain && ! $user->canLoginThrough($currentDomain)) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'حساب شما اجازه ورود از این دامنه را ندارد.']);
        }

        return $next($request);
    }
}
