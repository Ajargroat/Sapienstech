<?php

namespace App\Support;

/**
 * Single source of truth for the profile hub's sections.
 *
 * The hub is ONE route (`…settings.profile`); every personal part —
 * account details, edit (including password), the appearance studio, chat settings —
 * renders through it with a `?tab=` selector instead of owning a route of
 * its own, so the top navigation needs nothing but a direct link to the
 * profile. Sections still disappear the moment their feature flag is off,
 * which keeps the home page's settings list and the actual access control
 * (enforced in the hub controller) in sync.
 *
 * Both portals build their sections here so the consultant and student
 * shells stay visually and structurally identical.
 *
 * The hub home itself is a section (`profile`): the Telegram-style identity
 * page whose settings list links to every other section. Home is never a
 * row in that list, which is why the home views filter `key === 'profile'`
 * out of `visible()`.
 *
 * `feature` is a `features.*` key resolved through site(); students have no
 * studio/chat section yet, which is why their list is shorter — not a
 * different mechanism. `icon` feeds the settings-list rows (FontAwesome).
 */
class SettingsTabs
{
    /** Route name of the one page that hosts every section. */
    public static function hub(string $portal = 'consultant'): string
    {
        return $portal === 'student'
            ? 'student.settings.profile'
            : 'consultant.settings.profile';
    }

    /**
     * @return list<array{key: string, label_key: string, fallback: string, feature: string, icon: string}>
     */
    public static function forConsultant(): array
    {
        // The appearance studio is no longer a hub section: it lives on its
        // own /studio page (opened from the dashboard in a new tab), so the
        // `appearance` key is gone from the tab list and the legacy
        // `?tab=appearance` selector redirects there (ProfileController).
        return [
            ['key' => 'profile',    'label_key' => 'settings_profile',     'fallback' => 'پروفایل',          'feature' => 'settings_profile', 'icon' => 'fa-user'],
            ['key' => 'edit',       'label_key' => 'settings_profile_edit', 'fallback' => 'ویرایش پروفایل',  'feature' => 'settings_profile', 'icon' => 'fa-user-pen'],

            ['key' => 'chat',       'label_key' => 'settings_chat',        'fallback' => 'گفتگو',            'feature' => 'settings_chat',    'icon' => 'fa-comments'],
        ];
    }

    /**
     * The student portal has no studio/chat section yet; account and edit
     * use the same mechanism. Dropping another entry in here gives
     * it a row, a feature gate and a URL in one move.
     *
     * @return list<array{key: string, label_key: string, fallback: string, feature: string, icon: string}>
     */
    public static function forStudent(): array
    {
        return [
            ['key' => 'profile',  'label_key' => 'settings_profile',      'fallback' => 'پروفایل',         'feature' => 'settings_profile', 'icon' => 'fa-user'],
            ['key' => 'edit',     'label_key' => 'settings_profile_edit', 'fallback' => 'ویرایش پروفایل', 'feature' => 'settings_profile', 'icon' => 'fa-user-pen'],

        ];
    }

    /**
     * Feature-enabled sections with their absolute tab URLs and row icons.
     *
     * @return list<array{key: string, label: string, url: string, icon: string}>
     */
    public static function visible(string $portal = 'consultant'): array
    {
        $user = auth('web')->user();
        if ($portal !== 'student' && $user
            && ($user->isTeacher() || (tenant()?->hierarchy_type && ! $user->isTenantOwner()))) {
            return [];
        }

        $tabs = $portal === 'student' ? self::forStudent() : self::forConsultant();

        $visible = [];

        foreach ($tabs as $tab) {
            if (! (bool) site("features.{$tab['feature']}", false)) {
                continue;
            }

            $visible[] = [
                'key'   => $tab['key'],
                'label' => (string) site("labels.{$tab['label_key']}", $tab['fallback']),
                'icon'  => $tab['icon'],
                'url'   => $tab['key'] === 'profile'
                    ? route(self::hub($portal))
                    : route(self::hub($portal), ['tab' => $tab['key']]),
            ];
        }

        return $visible;
    }
}
