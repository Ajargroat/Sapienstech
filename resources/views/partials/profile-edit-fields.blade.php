{{--
    Shared ?tab=edit section (both portals), Telegram's "Edit Profile" in
    spirit: the identity header on top (the hero again, this time with a
    camera badge over the avatar), then grouped rows of
    icon / label + current value / chevron. Tapping a row expands an inline
    editor in place of the value — no stacked form boxes, no page of bare
    inputs (profile-edit.js does the toggling; a row left open server-side
    by a validation error renders already expanded).

    $profile — the signed-in account (User or Student)
    $portal  — 'consultant'|'student'

    All surfaces read tenant theme tokens through the hub's shared classes,
    so the section re-skins with the tenant theme. Text rows share one PATCH
    form (the update endpoint expects the full set), while the photo keeps
    its own multipart PUT/DELETE pair; picking a file uploads immediately.
--}}
@php
    $profileRoute = fn (string $suffix) => route($portal . '.settings.profile' . $suffix);
@endphp

<div class="profile-page profile-edit-fields">
    @include('partials.profile-hero', [
        'profile' => $profile,
        'portal' => $portal,
        'avatarPickerId' => 'profile-avatar-file',
    ])

    {{-- Photo --}}
    <div class="profile-info-list">
        <div class="profile-edit-row" data-edit-row="avatar">
            <button type="button" class="profile-edit-row-head" aria-expanded="false">
                <span class="profile-setting-icon"><i class="fas fa-camera" aria-hidden="true"></i></span>
                <span class="profile-edit-row-content">
                    <span class="profile-edit-row-label">تصویر پروفایل</span>
                    <span class="profile-edit-row-value">{{ $profile->avatar ? 'تغییر تصویر' : 'افزودن تصویر' }}</span>
                </span>
                <i class="fas fa-chevron-left profile-setting-chevron" aria-hidden="true"></i>
            </button>

            <div class="profile-edit-row-editor">
                <form method="POST" action="{{ $profileRoute('.avatar') }}" enctype="multipart/form-data" data-router="off" class="profile-edit-avatar-form">
                    @csrf
                    @method('PUT')
                    <label class="file-picker">
                        <i class="fas fa-image" aria-hidden="true"></i>
                        <span>انتخاب فایل</span>
                        <input type="file" name="avatar" id="profile-avatar-file" accept="image/*" hidden>
                    </label>
                    <button type="submit" class="primary-button profile-edit-save">بارگذاری</button>
                </form>
                <p class="profile-edit-note">بلافاصله پس از انتخاب فایل، تصویر جایگزین می‌شود.</p>

                @if($profile->avatar)
                    <form method="POST" action="{{ $profileRoute('.avatar.delete') }}" data-router="off">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link-danger"><i class="fas fa-trash" aria-hidden="true"></i> حذف تصویر</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Account fields (one PATCH form; every row's "ذخیره" submits it) --}}
    <form method="POST" action="{{ $profileRoute('.update') }}">
        @csrf
        @method('PATCH')

        <div class="profile-info-list">
            <div class="profile-edit-row @error('name') is-editing @enderror" data-edit-row="name">
                <button type="button" class="profile-edit-row-head" aria-expanded="{{ $errors->has('name') ? 'true' : 'false' }}">
                    <span class="profile-setting-icon"><i class="fas fa-user" aria-hidden="true"></i></span>
                    <span class="profile-edit-row-content">
                        <span class="profile-edit-row-label">نام</span>
                        <span class="profile-edit-row-value">{{ old('name', $profile->name) }}</span>
                    </span>
                    <i class="fas fa-chevron-left profile-setting-chevron" aria-hidden="true"></i>
                </button>

                <div class="profile-edit-row-editor">
                    <input type="text" name="name" value="{{ old('name', $profile->name) }}"
                           class="settings-input @error('name') is-invalid @enderror" required>
                    @error('name')<span class="settings-error">{{ $message }}</span>@enderror
                    <div class="profile-edit-row-buttons">
                        <button type="submit" class="primary-button profile-edit-save">ذخیره</button>
                        <button type="button" class="secondary-button" data-edit-cancel>انصراف</button>
                    </div>
                </div>
            </div>

            <div class="profile-edit-row @error('email') is-editing @enderror" data-edit-row="email">
                <button type="button" class="profile-edit-row-head" aria-expanded="{{ $errors->has('email') ? 'true' : 'false' }}">
                    <span class="profile-setting-icon"><i class="fas fa-at" aria-hidden="true"></i></span>
                    <span class="profile-edit-row-content">
                        <span class="profile-edit-row-label">ایمیل (شناسهٔ حساب شما)</span>
                        <span class="profile-edit-row-value is-accent" dir="ltr">{{ old('email', $profile->email) }}</span>
                    </span>
                    <i class="fas fa-chevron-left profile-setting-chevron" aria-hidden="true"></i>
                </button>

                <div class="profile-edit-row-editor">
                    <input type="email" name="email" value="{{ old('email', $profile->email) }}" dir="ltr"
                           class="settings-input @error('email') is-invalid @enderror" required>
                    @error('email')<span class="settings-error">{{ $message }}</span>@enderror
                    <div class="profile-edit-row-buttons">
                        <button type="submit" class="primary-button profile-edit-save">ذخیره</button>
                        <button type="button" class="secondary-button" data-edit-cancel>انصراف</button>
                    </div>
                </div>
            </div>

            <div class="profile-edit-row @error('bio') is-editing @enderror" data-edit-row="bio">
                <button type="button" class="profile-edit-row-head" aria-expanded="{{ $errors->has('bio') ? 'true' : 'false' }}">
                    <span class="profile-setting-icon"><i class="fas fa-circle-info" aria-hidden="true"></i></span>
                    <span class="profile-edit-row-content">
                        <span class="profile-edit-row-label">توضیحات</span>
                        @if(trim((string) old('bio', $profile->bio)) !== '')
                            <span class="profile-edit-row-value">{{ old('bio', $profile->bio) }}</span>
                        @else
                            <span class="profile-edit-row-value is-muted">هنوز متنی ننوشته‌اید</span>
                        @endif
                    </span>
                    <i class="fas fa-chevron-left profile-setting-chevron" aria-hidden="true"></i>
                </button>

                <div class="profile-edit-row-editor">
                    <textarea name="bio" rows="3" maxlength="500"
                              class="settings-input @error('bio') is-invalid @enderror"
                              placeholder="چند خط دربارهٔ خودتان — در پروفایل شما نمایش داده می‌شود.">{{ old('bio', $profile->bio) }}</textarea>
                    @error('bio')<span class="settings-error">{{ $message }}</span>@enderror
                    <div class="profile-edit-row-buttons">
                        <button type="submit" class="primary-button profile-edit-save">ذخیره</button>
                        <button type="button" class="secondary-button" data-edit-cancel>انصراف</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <p class="profile-edit-hint">برای ویرایش هر مورد، روی آن بزنید.</p>
</div>
