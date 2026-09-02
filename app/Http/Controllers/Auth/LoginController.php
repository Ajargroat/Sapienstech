<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $tenant = tenant() ?? abort(404);
        $domain = domain() ?? abort(404);

        $user = User::where('email', $credentials['email'])
            ->where('tenant_id', $tenant->id)
            ->first();

        $ok = $user
            && Hash::check($credentials['password'], $user->password)
            && $user->canLoginThrough($domain);

        if (! $ok) {
            // Deliberately generic: don't reveal whether the failure was
            // the password, the domain assignment, or the account itself.
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        Auth::login($user, $request->boolean('remember'));

        // Prevent session fixation.
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
