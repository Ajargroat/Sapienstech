{{--
    Telegram-style body of the profile hub home (consultant + student),
    rendered under the shared identity hero:

    1. action bar   — quick tiles for the portal's main features (the same
                      destinations the top navigation carries),
    2. info list    — the account's data rows; the login identifier
                      (email, phone or کد ملی) gets the accent color,
                      exactly like Telegram's @username,
    3. settings list — one row per profile section from App\Support\SettingsTabs
                      (edit, appearance, chat — feature-gated), each
                      a link *into* the hub's ?tab= sections. The 'profile'
                      key is this page itself, so it never lists itself,
    4. danger zone  — logout as a plain red row.

    $profile — User|Student   $portal — 'consultant'|'student'
    $actions — list<array{label, icon, url}> built by each portal's home view
    $tabs    — SettingsTabs::visible($portal), shared with the hub layout

    The settings list opts into data-router="replace" (like the tab bars it
    replaces): its links only change the query, so the router must swap the
    whole #app-content rather than attempt a same-path partial update.
--}}
@php
    $logoutRoute = $portal === 'student' ? 'student.logout' : 'logout';
    $handle = $profile->loginIdentifier();
    $handleKey = $handle === null ? null
        : (str_contains($handle, '@') ? 'email'
        : (preg_match('/^09\d{9}$/', $handle) === 1 ? 'phone' : 'national_code'));
@endphp
<div class="profile-page">
    @if(! empty($actions))
        <section class="profile-actions" aria-label="دسترسی سریع">
            @foreach($actions as $action)
                <a class="profile-action" href="{{ $action['url'] }}">
                    <i class="fas {{ $action['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $action['label'] }}</span>
                </a>
            @endforeach
        </section>
    @endif

    <section class="profile-info-list">
        <div class="profile-info-row">
            <span class="profile-info-label">{{ $labels['phone'] ?? 'شمارهٔ موبایل' }}</span>
            <span class="profile-info-value {{ $profile->phone ? '' : 'is-muted' }}{{ $handleKey === 'phone' ? ' is-accent' : '' }}" dir="ltr">{{ $profile->phone ?: 'ثبت نشده' }}</span>
        </div>
        <div class="profile-info-row">
            <span class="profile-info-label">{{ $labels['national_code'] ?? 'کد ملی' }}</span>
            <span class="profile-info-value {{ $profile->national_code ? '' : 'is-muted' }}{{ $handleKey === 'national_code' ? ' is-accent' : '' }}" dir="ltr">{{ $profile->national_code ?: 'ثبت نشده' }}</span>
        </div>
        <div class="profile-info-row">
            <span class="profile-info-label">{{ $labels['email'] ?? 'ایمیل' }}</span>
            <span class="profile-info-value {{ $profile->email ? '' : 'is-muted' }}{{ $handleKey === 'email' ? ' is-accent' : '' }}" dir="ltr">{{ $profile->email ?: 'ثبت نشده' }}</span>
        </div>
        <div class="profile-info-row">
            <span class="profile-info-label">{{ $labels['bio'] ?? 'توضیحات' }}</span>
            <span class="profile-info-value {{ $profile->bio ? '' : 'is-muted' }}">
                {{ $profile->bio ?: 'هنوز توضیحی برای این پروفایل نوشته نشده.' }}
            </span>
        </div>
    </section>

    <nav class="profile-settings-list" data-router="replace" aria-label="{{ $labels['settings'] ?? 'تنظیمات' }}">
        @foreach(($tabs ?? []) as $section)
            @continue($section['key'] === 'profile')
            <a class="profile-setting-row" href="{{ $section['url'] }}">
                <span class="profile-setting-icon"><i class="fas {{ $section['icon'] }}" aria-hidden="true"></i></span>
                <span class="profile-setting-label">{{ $section['label'] }}</span>
                <i class="fas fa-chevron-left profile-setting-chevron" aria-hidden="true"></i>
            </a>
        @endforeach
    </nav>

    <section class="profile-danger">
        <form method="POST" action="{{ route($logoutRoute) }}" data-router="off">
            @csrf
            <button type="submit" class="profile-danger-btn">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                {{ $labels['logout'] ?? 'خروج از حساب کاربری' }}
            </button>
        </form>
    </section>
</div>
