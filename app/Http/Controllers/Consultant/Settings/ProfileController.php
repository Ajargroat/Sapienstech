<?php

namespace App\Http\Controllers\Consultant\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\Settings\ChangePasswordRequest;
use App\Http\Requests\Consultant\Settings\UpdateAvatarRequest;
use App\Http\Requests\Consultant\Settings\UpdateProfileRequest;
use App\Support\SettingsTabs;
use App\Support\TenantUploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The consultant's own account settings — the Profile tab of the settings
 * hub. Tenant-level identity (site name, logo, colors) is *not* here; that
 * belongs to the appearance studio. This page is per-user only, which is
 * also why logout lives here rather than in the top navigation.
 */
class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('consultant.settings.profile', [
            'user' => $request->user(),
            'tabs' => SettingsTabs::visible('consultant'),
            'activeTab' => 'profile',
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated())->save();

        return back()->with('success', 'پروفایل به‌روزرسانی شد.');
    }

    public function updatePassword(ChangePasswordRequest $request): RedirectResponse
    {
        // The model's `hashed` cast does the rehashing; never store the raw.
        $request->user()->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        return back()->with('success', 'رمز عبور تغییر کرد.');
    }

    public function updateAvatar(UpdateAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->avatar = TenantUploads::store(
            $request->file('avatar'),
            'avatars',
            $user->avatar,
        );
        $user->save();

        return back()->with('success', 'تصویر پروفایل بارگذاری شد.');
    }

    public function deleteAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        TenantUploads::delete($user->avatar);
        $user->forceFill(['avatar' => null])->save();

        return back()->with('success', 'تصویر پروفایل حذف شد.');
    }
}
