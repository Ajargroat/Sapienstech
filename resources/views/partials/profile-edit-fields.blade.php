{{-- Shared account editors. Each dialog posts the full profile field set expected by the endpoint. --}}
@php
    $profileRoute = fn (string $suffix) => route($portal . '.settings.profile' . $suffix);
    $fields = [
        'name' => ['label' => 'نام', 'icon' => 'fa-user', 'type' => 'text'],
        'email' => ['label' => 'ایمیل (شناسهٔ حساب شما)', 'icon' => 'fa-at', 'type' => 'email'],
        'bio' => ['label' => 'توضیحات', 'icon' => 'fa-circle-info', 'type' => 'textarea'],
    ];
@endphp

<div class="profile-page profile-edit-fields" data-profile-dialogs>
    @include('partials.profile-hero', [
        'profile' => $profile,
        'portal' => $portal,
        'avatarDialogId' => 'profile-dialog-avatar',
    ])


    <dialog class="profile-dialog" id="profile-dialog-avatar" aria-label="تصویر پروفایل" @if($errors->has('avatar')) data-profile-auto-open @endif>

        <div class="profile-dialog-body">
            <form method="POST" action="{{ $profileRoute('.avatar') }}" enctype="multipart/form-data" data-router="off" class="profile-edit-avatar-form">
                @csrf
                @method('PUT')
                <label class="file-picker">
                    <i class="fas fa-image" aria-hidden="true"></i>
                    <span>انتخاب فایل</span>
                    <input type="file" name="avatar" accept="image/*" required aria-label="انتخاب تصویر پروفایل">
                </label>
                <button type="submit" class="primary-button profile-edit-save">بارگذاری</button>
            </form>
            <p class="profile-edit-note">بلافاصله پس از انتخاب فایل، تصویر جایگزین می‌شود.</p>
            @error('avatar')<span class="settings-error" role="alert">{{ $message }}</span>@enderror

        </div>
    </dialog>

    <div class="profile-info-list">
        @foreach($fields as $name => $field)
            <div class="profile-edit-row">
                <button type="button" class="profile-edit-row-head" data-profile-open="profile-dialog-{{ $name }}" aria-haspopup="dialog" aria-controls="profile-dialog-{{ $name }}">
                    <span class="profile-setting-icon"><i class="fas {{ $field['icon'] }}" aria-hidden="true"></i></span>
                    <span class="profile-edit-row-content">
                        <span class="profile-edit-row-label">{{ $field['label'] }}</span>
                        <span class="profile-edit-row-value {{ $name === 'email' ? 'is-accent' : '' }}" @if($name === 'email') dir="ltr" @endif>{{ $profile->{$name} ?: 'هنوز متنی ننوشته‌اید' }}</span>
                    </span>
                    <i class="fas fa-chevron-left profile-setting-chevron" aria-hidden="true"></i>
                </button>
            </div>
        @endforeach
    </div>

    @foreach($fields as $name => $field)
        <dialog class="profile-dialog" id="profile-dialog-{{ $name }}" aria-label="{{ $field['label'] }}" @if($errors->has($name)) data-profile-auto-open @endif>

            <form method="POST" action="{{ $profileRoute('.update') }}" class="profile-dialog-body">
                @csrf
                @method('PATCH')
                @foreach($fields as $otherName => $otherField)
                    @if($otherName !== $name)
                        <input type="hidden" name="{{ $otherName }}" value="{{ $profile->{$otherName} }}">
                    @endif
                @endforeach
                <label class="profile-edit-row-label" for="profile-input-{{ $name }}">{{ $field['label'] }}</label>
                @if($field['type'] === 'textarea')
                    <textarea id="profile-input-{{ $name }}" name="{{ $name }}" rows="4" maxlength="500" class="settings-input" autofocus aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @error($name) aria-describedby="profile-error-{{ $name }}" @enderror>{{ old($name, $profile->{$name}) }}</textarea>
                @else
                    <input id="profile-input-{{ $name }}" type="{{ $field['type'] }}" name="{{ $name }}" value="{{ old($name, $profile->{$name}) }}" class="settings-input" required autofocus autocomplete="{{ $name }}" @if($name === 'email') dir="ltr" @endif aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @error($name) aria-describedby="profile-error-{{ $name }}" @enderror>
                @endif
                @error($name)<span class="settings-error" id="profile-error-{{ $name }}" role="alert">{{ $message }}</span>@enderror
                <div class="profile-dialog-actions">
                    <button type="button" class="secondary-button" data-profile-close>انصراف</button>
                    <button type="submit" class="primary-button">ذخیره</button>
                </div>
            </form>
        </dialog>
    @endforeach
    @include('partials.profile-password-fields', ['portal' => $portal])

    <p class="profile-edit-hint">برای ویرایش هر مورد، روی آن بزنید.</p>
</div>
