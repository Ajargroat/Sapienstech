<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Single source of truth for the settings-hub tab bar.
 *
 * Both portals build their tabs here so the consultant and student shells
 * stay visually and structurally identical, and so a tab disappears as soon
 * as its feature flag is off or its route has not been registered yet —
 * which lets the hub ship incrementally (profile first, blog/appearance as
 * those phases land) without the view hard-coding a list that can go stale.
 *
 * `feature` is a `features.*` key resolved through site(), matching the
 * `consultant.feature:*` middleware gating the routes themselves; the tab
 * list and the actual access control can therefore never disagree.
 */
class SettingsTabs
{
    /**
     * @return list<array{key: string, label_key: string, fallback: string, route: string, feature: string}>
     */
    public static function forConsultant(): array
    {
        return [
            ['key' => 'profile',    'label_key' => 'settings_profile',    'fallback' => 'پروفایل', 'route' => 'consultant.settings.profile',    'feature' => 'settings_profile'],
            ['key' => 'blog',       'label_key' => 'settings_blog',       'fallback' => 'وبلاگ',   'route' => 'consultant.settings.blog.index', 'feature' => 'blog_management'],
            ['key' => 'appearance', 'label_key' => 'settings_appearance', 'fallback' => 'ظاهر',    'route' => 'consultant.settings.appearance', 'feature' => 'theme_studio'],
            ['key' => 'chat',       'label_key' => 'settings_chat',       'fallback' => 'گفتگو',   'route' => 'consultant.settings.chat',       'feature' => 'settings_chat'],
        ];
    }

    /**
     * The student portal joins the same hub later; profile is its only tab
     * for now, but the dropdown and layout are already shared.
     *
     * @return list<array{key: string, label_key: string, fallback: string, route: string, feature: string}>
     */
    public static function forStudent(): array
    {
        return [
            ['key' => 'profile', 'label_key' => 'settings_profile', 'fallback' => 'پروفایل', 'route' => 'student.settings.profile', 'feature' => 'settings_profile'],
        ];
    }

    /**
     * Tabs that are both feature-enabled and actually routable.
     *
     * @return list<array{key: string, label: string, route: string}>
     */
    public static function visible(string $portal = 'consultant'): array
    {
        $tabs = $portal === 'student' ? self::forStudent() : self::forConsultant();

        $visible = [];

        foreach ($tabs as $tab) {
            if (! Route::has($tab['route'])) {
                continue;
            }

            if (! (bool) site("features.{$tab['feature']}", false)) {
                continue;
            }

            $visible[] = [
                'key'   => $tab['key'],
                'label' => (string) site("labels.{$tab['label_key']}", $tab['fallback']),
                'route' => $tab['route'],
            ];
        }

        return $visible;
    }
}
