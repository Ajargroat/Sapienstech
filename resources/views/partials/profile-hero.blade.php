{{--
    Telegram-style identity block at the top of the profile hub (both
    portals): centered avatar, name, then a one-line colored status.

    $profile — the signed-in account (User or Student)

    $portal  — 'consultant'|'student'
    $avatarPickerId — optional: id of a file input elsewhere on the page
                     (?tab=edit's upload form). When set, the avatar gains
                     Telegram's camera badge and the avatar itself becomes
                     the picker's label.

    The status line is where each portal's identity lives: consultants show
    their role, students their grade and major (X surfaces workplace/education
    the same way) — and, per product decision, never gender. Everything reads
    tenant theme tokens, so this block re-skins with the tenant's theme and
    the live color schemes without a line of JS. Editing the account (name,
    bio, avatar) lives behind the "ویرایش پروفایل" row of the settings list.
--}}
@php
    $status = match (true) {
        $portal === 'student' => (string) collect([
            $profile->grade ? 'پایه ' . $profile->grade : null,
            $profile->major ? 'رشته ' . $profile->major : null,
        ])->filter()->implode(' · ') ?: 'دانش‌آموز',
        ($profile->role ?? null) === \App\Models\User::ROLE_TENANT_ADMIN => 'مدیر مجموعه',
        ($profile->role ?? null) === \App\Models\User::ROLE_CONSULTANT_STAFF => 'مشاور',
        default => 'مشاور',
    };
@endphp
<header class="profile-hero">
    <div class="profile-hero-avatar-wrap">
        <div class="profile-hero-avatar">

            @if($profile->avatar)

                <img src="{{ tenant_asset($profile->avatar) }}" alt="{{ $profile->name }}">

            @else

                <span class="profile-hero-avatar-initial">{{ mb_substr($profile->name, 0, 1) }}</span>

            @endif

        </div>

        @if(!empty($avatarPickerId))
            <label class="profile-edit-avatar-badge" for="{{ $avatarPickerId }}" title="تغییر تصویر">
                <i class="fas fa-camera" aria-hidden="true"></i>
            </label>
        @endif
    </div>

    <h1 class="profile-hero-name">{{ $profile->name }}</h1>
    <p class="profile-hero-status">{{ $status }}</p>
</header>
