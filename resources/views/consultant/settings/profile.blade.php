@extends('consultant.settings.layout')

@section('settings-content')
<div class="settings-cards">
    {{-- Identity + avatar --}}
    <section class="settings-card">
        <h3 class="settings-card-title">{{ $labels['settings_profile'] ?? 'پروفایل' }}</h3>

        <div class="profile-id-row">
            @if($user->avatar)
                <img class="profile-avatar" src="{{ tenant_asset($user->avatar) }}" alt="{{ $user->name }}">
            @else
                <span class="profile-avatar profile-avatar--initial">{{ mb_substr($user->name, 0, 1) }}</span>
            @endif

            <div class="profile-avatar-actions">
                <form method="POST" action="{{ route('consultant.settings.profile.avatar') }}" enctype="multipart/form-data" data-router="off">
                    @csrf
                    @method('PUT')
                    <label class="file-picker">
                        <i class="fas fa-camera" aria-hidden="true"></i>
                        <span>{{ $user->avatar ? 'تغییر تصویر' : 'بارگذاری تصویر' }}</span>
                        <input type="file" name="avatar" accept="image/*" hidden>
                    </label>
                    <button type="submit" class="secondary-button">ذخیره</button>
                </form>

                @if($user->avatar)
                    <form method="POST" action="{{ route('consultant.settings.profile.avatar.delete') }}" data-router="off">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link-danger"><i class="fas fa-trash" aria-hidden="true"></i> حذف تصویر</button>
                    </form>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('consultant.settings.profile.update') }}" class="settings-form">
            @csrf
            @method('PATCH')

            <label class="settings-field">
                <span class="settings-field-label">نام</span>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="settings-input @error('name') is-invalid @enderror" required>
                @error('name')<span class="settings-error">{{ $message }}</span>@enderror
            </label>

            <label class="settings-field">
                <span class="settings-field-label">ایمیل</span>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="settings-input @error('email') is-invalid @enderror" required>
                @error('email')<span class="settings-error">{{ $message }}</span>@enderror
            </label>

            <button type="submit" class="primary-button">ذخیره تغییرات</button>
        </form>
    </section>

    {{-- Password --}}
    <section class="settings-card">
        <h3 class="settings-card-title">رمز عبور</h3>

        <form method="POST" action="{{ route('consultant.settings.profile.password') }}" class="settings-form">
            @csrf
            @method('PUT')

            <label class="settings-field">
                <span class="settings-field-label">رمز عبور فعلی</span>
                <input type="password" name="current_password" autocomplete="current-password" class="settings-input @error('current_password') is-invalid @enderror" required>
                @error('current_password')<span class="settings-error">{{ $message }}</span>@enderror
            </label>

            <label class="settings-field">
                <span class="settings-field-label">رمز عبور جدید</span>
                <input type="password" name="password" autocomplete="new-password" class="settings-input @error('password') is-invalid @enderror" required>
                @error('password')<span class="settings-error">{{ $message }}</span>@enderror
            </label>

            <label class="settings-field">
                <span class="settings-field-label">تکرار رمز عبور جدید</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" class="settings-input" required>
            </label>

            <button type="submit" class="primary-button">تغییر رمز عبور</button>
        </form>
    </section>

    {{-- Logout: moved out of the top navigation to its correct home --}}
    <section class="settings-card settings-card--danger">
        <h3 class="settings-card-title">خروج از حساب</h3>
        <p class="settings-card-text">با خروج، نشست شما در این مرورگر بسته می‌شود.</p>
        <form method="POST" action="{{ route('logout') }}" data-router="off">
            @csrf
            <button type="submit" class="danger-button">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i> خروج از حساب کاربری
            </button>
        </form>
    </section>
</div>
@endsection
