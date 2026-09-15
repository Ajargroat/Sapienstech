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
 * The student-side profile hub — the counterpart of the consultant
 * ProfileController: same single route with `?tab=` section dispatch and
 * the same SettingsTabs gating (account, edit, password), so both portals
 * browse identically and a disabled feature 404s a tab exactly like the
 * consultant shell. ApplyPersonalTheme gives the student guard the same
 * per-user theme machinery.
 *
 * Email uniqueness is scoped to the students table within the tenant, mirroring
 * the (tenant_id, email) constraint on that table.
 */
class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $sections = collect(SettingsTabs::visible('student'))->keyBy('key');

        abort_if($sections->isEmpty(), 404);

        $tab = (string) $request->query('tab', '');

        // An explicit tab must be one the tenant can actually see — same
        // rule the consultant hub applies through SettingsTabs.
        abort_if($tab !== '' && ! $sections->has($tab), 404);

        if ($tab === '') {
            $tab = $sections->has('profile') ? 'profile' : $sections->keys()->first();
        }

        $student = $request->user('student');

        return match ($tab) {
            'edit' => view('student.settings.edit', [
                'student' => $student,
                'tabs' => SettingsTabs::visible('student'),
                'activeTab' => 'edit',
            ]),
            'password' => view('student.settings.password', [
                'student' => $student,
                'tabs' => SettingsTabs::visible('student'),
                'activeTab' => 'password',
            ]),
            default => view('student.settings.profile', [
                'student' => $student,
                'tabs' => SettingsTabs::visible('student'),
                'activeTab' => 'profile',
            ]),
        };
    }

    public function update(Request $request): RedirectResponse
    {
        $student = $request->user('student');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Email uniqueness is scoped to the students table within the tenant,
            // mirroring the (tenant_id, email) constraint on that table.
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('students', 'email')
                    ->where(fn ($q) => $q->where('tenant_id', $student->tenant_id))
                    ->ignore($student->id),
            ],
            // The bio line shown under the name in the profile header.
            'bio' => ['nullable', 'string', 'max:500'],
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
