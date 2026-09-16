{{--
    Shared ?tab=password section (both portals), carrying over the edit
    card's structure: identity hero on top, then grouped rows of
    icon / label + control. Unlike the edit rows there is no stored value
    to reveal by tapping — the three password fields are blank by nature —
    so each row keeps its input permanently in place of the value line,
    and one submit closes the card. PUT goes to the same endpoint as
    before; the model's hashed cast does the rehashing and the
    current-password rule is enforced server-side (ChangePasswordRequest).

    $profile — the signed-in account (User or Student)
    $portal  — 'consultant'|'student'

    Rows read tenant theme tokens through the hub's shared classes, so the
    section re-skins with the tenant's theme like every other surface.
--}}
@php
    $passwordRoute = route($portal . '.settings.profile.password');
@endphp

<div class="profile-page profile-password-fields">
    @include('partials.profile-hero', ['profile' => $profile, 'portal' => $portal])

    <form method="POST" action="{{ $passwordRoute }}">
        @csrf
        @method('PUT')

        <div class="profile-info-list">
            <label class="profile-password-row">
                <span class="profile-setting-icon"><i class="fas fa-key" aria-hidden="true"></i></span>
                <span class="profile-edit-row-content">
                    <span class="profile-edit-row-label">رمز عبور فعلی</span>
                    <input type="password" name="current_password" autocomplete="current-password" dir="ltr"
                           class="settings-input @error('current_password') is-invalid @enderror" required>
                    @error('current_password')<span class="settings-error">{{ $message }}</span>@enderror
                </span>
            </label>

            <label class="profile-password-row">
                <span class="profile-setting-icon"><i class="fas fa-lock" aria-hidden="true"></i></span>
                <span class="profile-edit-row-content">
                    <span class="profile-edit-row-label">رمز عبور جدید</span>
                    <input type="password" name="password" autocomplete="new-password" dir="ltr"
                           class="settings-input @error('password') is-invalid @enderror" required>
                    @error('password')<span class="settings-error">{{ $message }}</span>@enderror
                </span>
            </label>

            <label class="profile-password-row">
                <span class="profile-setting-icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>
                <span class="profile-edit-row-content">
                    <span class="profile-edit-row-label">تکرار رمز عبور جدید</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" dir="ltr"
                           class="settings-input" required>
                </span>
            </label>
        </div>

        <button type="submit" class="primary-button profile-password-submit">
            <i class="fas fa-floppy-disk" aria-hidden="true"></i> تغییر رمز عبور
        </button>
    </form>

    <p class="profile-edit-hint">رمز عبور جدید باید حداقل ۸ نویسه باشد.</p>
</div>
