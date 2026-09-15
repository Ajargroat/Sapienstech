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
 * The profile hub: the one page the top navigation's profile button leads
 * to. Its home is the Telegram-style identity page (centered avatar, quick
 * actions, info rows, a settings list) shared by both portals, and every
 * personal part of the consultant area — profile edit, password, the
 * appearance studio, chat settings — renders *through* this route with a
 * `?tab=<section>` selector instead of owning a page route. The save
 * endpoints stay separate; only the browsing surface is unified.
 *
 * Section visibility (and therefore `?tab=` access) is decided by
 * App\Support\SettingsTabs from the same `features.*` switches the old
 * per-tab route middleware read, so a disabled section 404s exactly like it
 * used to.
 */
class ProfileController extends Controller
{
    public function index(Request $request, AppearanceController $appearance, ChatSettingsController $chat): View
    {
        $sections = collect(SettingsTabs::visible('consultant'))->keyBy('key');

        abort_if($sections->isEmpty(), 404);

        $tab = (string) $request->query('tab', '');

        // An explicit tab must be one the tenant can actually see — the
        // equivalent of the `consultant.feature:*` middleware that used to
        // sit on each standalone tab route.
        abort_if($tab !== '' && ! $sections->has($tab), 404);

        if ($tab === '') {
            $tab = $sections->has('profile') ? 'profile' : $sections->keys()->first();
        }

        return match ($tab) {
            'appearance' => $appearance->index($request),
            'chat' => $chat->index($request),
            'edit' => view('consultant.settings.edit', [
                'user' => $request->user(),
                'tabs' => SettingsTabs::visible('consultant'),
                'activeTab' => 'edit',
            ]),
            'password' => view('consultant.settings.password', [
                'user' => $request->user(),
                'tabs' => SettingsTabs::visible('consultant'),
                'activeTab' => 'password',
            ]),
            default => view('consultant.settings.profile', [
                'user' => $request->user(),
                'tabs' => SettingsTabs::visible('consultant'),
                'activeTab' => 'profile',
            ]),
        };
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
