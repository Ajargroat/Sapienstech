<?php

namespace App\Http\Controllers\Student\Settings;

use App\Http\Controllers\Controller;
use App\Rules\IranianPhone;
use App\Rules\NationalCode;
use App\Support\IranianIdentity;
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
 * the same SettingsTabs gating (account and edit), so both portals
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

        // Canonicalize before validating so the unique rules and the model
        // setters agree on one stored form (Persian digits, +98 …). Only keys
        // the client sent are touched: an absent field stays untouched, an
        // empty one clears it, unparseable input stays as typed for the
        // format rules to reject.
        $normalized = [];

        if ($request->exists('phone')) {
            $phone = $request->input('phone');
            $normalized['phone'] = IranianIdentity::normalizePhone($phone) ?? ($phone === '' ? null : $phone);
        }

        if ($request->exists('national_code')) {
            $code = $request->input('national_code');
            $normalized['national_code'] = IranianIdentity::normalizeNationalCode($code) ?? ($code === '' ? null : $code);
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Email uniqueness is scoped to the students table within the tenant,
            // mirroring the (tenant_id, email) constraint on that table. It is
            // optional now: phone or کد ملی can carry the login instead.
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('students', 'email')
                    ->where(fn ($q) => $q->where('tenant_id', $student->tenant_id))
                    ->ignore($student->id),
            ],
            'phone' => [
                'nullable', 'string', 'max:20', new IranianPhone,
                Rule::unique('students', 'phone')
                    ->where(fn ($q) => $q->where('tenant_id', $student->tenant_id))
                    ->ignore($student->id),
            ],
            'national_code' => [
                'nullable', 'string', 'max:10',
                new NationalCode(available: true, ignoreStudentId: $student->id),
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
