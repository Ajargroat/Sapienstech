<?php

namespace App\Http\Controllers\Student\Settings;

use App\Http\Controllers\Controller;
use App\Support\SettingsTabs;
use App\Support\TenantUploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The student's own account settings — the student-side counterpart of the
 * consultant ProfileController, kept deliberately minimal (name, email,
 * password, avatar, logout). It exists so the shared settings skeleton
 * (SettingsTabs, ApplyPersonalTheme, the tab layout) is exercised end-to-end
 * for the student guard, not just designed for it.
 *
 * Email uniqueness is scoped to the students table within the tenant, mirroring
 * the (tenant_id, email) constraint on that table.
 */
class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('student.settings.profile', [
            'student' => $request->user('student'),
            'tabs' => SettingsTabs::visible('student'),
            'activeTab' => 'profile',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $student = $request->user('student');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('students', 'email')
                    ->where(fn ($q) => $q->where('tenant_id', $student->tenant_id))
                    ->ignore($student->id),
            ],
        ]);

        $student->fill($data)->save();

        return back()->with('success', 'پروفایل به‌روزرسانی شد.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:student'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user('student')->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        return back()->with('success', 'رمز عبور تغییر کرد.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        $student = $request->user('student');

        $student->avatar = TenantUploads::store(
            $request->file('avatar'),
            'avatars',
            $student->avatar,
        );
        $student->save();

        return back()->with('success', 'تصویر پروفایل بارگذاری شد.');
    }

    public function deleteAvatar(Request $request): RedirectResponse
    {
        $student = $request->user('student');

        TenantUploads::delete($student->avatar);
        $student->forceFill(['avatar' => null])->save();

        return back()->with('success', 'تصویر پروفایل حذف شد.');
    }
}
