{{-- Both portals share the password dialog; validation and the PUT endpoint are unchanged. --}}
@php
    $passwordRoute = route($portal . '.settings.profile.password');
@endphp

<div class="profile-password-fields">

    <div class="profile-info-list">
        <button type="button" class="profile-edit-row-head" data-profile-open="profile-dialog-password" aria-haspopup="dialog" aria-controls="profile-dialog-password">
            <span class="profile-setting-icon"><i class="fas fa-lock" aria-hidden="true"></i></span>
            <span class="profile-edit-row-content">
                <span class="profile-edit-row-label">تغییر رمز عبور</span>
                <span class="profile-edit-row-value">برای امنیت حساب، رمز عبور منحصربه‌فرد انتخاب کنید.</span>
            </span>
            <i class="fas fa-chevron-left profile-setting-chevron" aria-hidden="true"></i>
        </button>
    </div>

    <dialog class="profile-dialog" id="profile-dialog-password" aria-label="تغییر رمز عبور" @if($errors->hasAny(['current_password', 'password', 'password_confirmation'])) data-profile-auto-open @endif>

    <form method="POST" action="{{ $passwordRoute }}" class="profile-dialog-body">
        @csrf
        @method('PUT')

        <div class="profile-info-list">
            <label class="profile-password-row">
                <span class="profile-setting-icon"><i class="fas fa-key" aria-hidden="true"></i></span>
                <span class="profile-edit-row-content">
                    <span class="profile-edit-row-label">رمز عبور فعلی</span>
                    <input type="password" name="current_password" autocomplete="current-password" dir="ltr" autofocus
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

        <p class="profile-edit-note">رمز عبور جدید باید حداقل ۸ نویسه باشد.</p>
        <div class="profile-dialog-actions">
            <button type="button" class="secondary-button" data-profile-close>انصراف</button>
            <button type="submit" class="primary-button">تغییر رمز عبور</button>
        </div>
    </form>
    </dialog>
</div>

