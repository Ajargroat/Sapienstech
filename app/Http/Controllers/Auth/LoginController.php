<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthSurface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * The login surface: a tenant's bespoke shell when one is published for
     * its own host, otherwise the shared card page.
     *
     * AuthSurface fails closed — an unknown slug, an unpublished folder or a
     * corrupt manifest all resolve to `null` — so a broken shell can never
     * take a tenant's login down. This is presentation dispatch ONLY: the
     * route, both guards' POST endpoints, CSRF, throttling and the
     * identifier rules are untouched below.
     */
    public function showLoginForm(): View
    {
        $shell = app(AuthSurface::class)->for(tenant());

        return view($shell ?? 'auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $tenant = tenant() ?? abort(404);
        $domain = domain() ?? abort(404);

        $user = User::whereIdentifier($credentials['identifier'])
            ->where('tenant_id', $tenant->id)
            ->first();

        $ok = $user
            && Hash::check($credentials['password'], $user->password)
            && $user->canLoginThrough($domain);

        if (! $ok) {
            // Deliberately generic: don't reveal whether the failure was
            // the password, the domain assignment, or the account itself.
            throw ValidationException::withMessages([
                'identifier' => trans('auth.failed'),
            ]);
        }

        Auth::login($user, $request->boolean('remember'));

        // Prevent session fixation.
        $request->session()->regenerate();

        // Teachers land on their own panel; everyone else on the consultant
        // dashboard (tenant admins included).
        $fallback = $user->isTeacher() ? route('teacher.dashboard') : route('consultant.dashboard');

        return redirect()->intended($fallback);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
