<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentLoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.student-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $tenant = tenant() ?? abort(404);

        // Tenant isolation: only look for students belonging to the current domain's tenant
        $student = Student::where('email', $credentials['email'])
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $student || ! Hash::check($credentials['password'], $student->password)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        Auth::guard('student')->login($student, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('student.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('student')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('student.login');
    }
}
