<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\WebsiteConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        $tenant = tenant() ?? abort(404);

        return view('auth.login', [
            'tenant' => $tenant,
            'config' => WebsiteConfig::where('tenant_id', $tenant->id)->first(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $tenant = tenant() ?? abort(404);

        // emails are unique per tenant only — always scope by the tenant
        // resolved from the domain, never from user input.
        $ok = Auth::attempt([
            'email'     => $credentials['email'],
            'password'  => $credentials['password'],
            'tenant_id' => $tenant->id,
        ], $request->boolean('remember'));

        if (! $ok) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('consultant.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
