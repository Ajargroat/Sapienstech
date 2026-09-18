@extends('consultant.settings.layout')

@section('settings-content')
@php
    $chatSections = [
        'access' => [
            'title' => 'دسترسی و گروه‌ها',
            'help' => 'دسترسی دانش‌آموزان و شیوهٔ تشکیل گروه‌های گفتگو را مشخص کنید.',
            'fields' => [
                ['path' => 'features.student_chat', 'type' => 'toggle', 'icon' => 'fa-graduation-cap', 'label' => 'گفتگو در پنل دانش‌آموز', 'help' => 'نمایش بخش گفتگو برای دانش‌آموزان مجموعه.'],
                ['path' => 'chat.groups.enabled', 'type' => 'toggle', 'icon' => 'fa-users', 'label' => 'ساخت گروه توسط مشاور', 'help' => 'مشاور بتواند گروهی از دانش‌آموزان بسازد.'],
                ['path' => 'chat.groups.max_members', 'type' => 'number', 'icon' => 'fa-user-plus', 'label' => 'ظرفیت هر گروه', 'help' => 'از ۲ تا ۵۰۰ عضو در هر گروه.', 'min' => 2, 'max' => 500, 'unit' => 'نفر'],
            ],
        ],
        'attachments' => [
            'title' => 'فایل‌ها و پیوست‌ها',
            'help' => 'اجازهٔ ارسال فایل و سقف حجم هر پیوست.',
            'fields' => [
                ['path' => 'chat.attachments.enabled', 'type' => 'toggle', 'icon' => 'fa-paperclip', 'label' => 'ارسال پیوست', 'help' => 'امکان افزودن فایل به پیام‌ها.'],
                ['path' => 'chat.attachments.max_kb', 'type' => 'number', 'icon' => 'fa-file', 'label' => 'حداکثر حجم هر فایل', 'help' => 'از ۱۶ تا ۲۰۴۸۰ کیلوبایت (۲۰ مگابایت).', 'min' => 16, 'max' => 20480, 'unit' => 'کیلوبایت'],
            ],
        ],
        'messages' => [
            'title' => 'پیام‌ها و وضعیت فعالیت',
            'help' => 'نشانه‌های فعالیت، طول پیام و محدودیت‌های ارسال و ویرایش.',
            'fields' => [
                ['path' => 'chat.read_receipts', 'type' => 'toggle', 'icon' => 'fa-check-double', 'label' => 'نشان خوانده‌شدن', 'help' => 'فرستنده از خوانده‌شدن پیام مطلع شود.'],
                ['path' => 'chat.typing_indicator', 'type' => 'toggle', 'icon' => 'fa-ellipsis', 'label' => 'نمایش «در حال نوشتن…»', 'help' => 'وضعیت نوشتن پیام در گفتگو نمایش داده شود.'],
                ['path' => 'chat.message_max_length', 'type' => 'number', 'icon' => 'fa-align-right', 'label' => 'حداکثر طول پیام', 'help' => 'از ۱۰۰ تا ۲۰۰۰۰ نویسه برای هر پیام.', 'min' => 100, 'max' => 20000, 'unit' => 'نویسه'],
                ['path' => 'chat.edit_window_minutes', 'type' => 'number', 'icon' => 'fa-pen', 'label' => 'مهلت ویرایش پیام', 'help' => 'از ۱ تا ۱۰۰۸۰ دقیقه؛ ۱۰۰۸۰ دقیقه برابر یک هفته است.', 'min' => 1, 'max' => 10080, 'unit' => 'دقیقه', 'placeholder' => '10080'],
                ['path' => 'chat.rate_limit_per_minute', 'type' => 'number', 'icon' => 'fa-gauge', 'label' => 'سقف ارسال در دقیقه', 'help' => 'از ۱ تا ۱۲۰ پیام در هر گفتگو.', 'min' => 1, 'max' => 120, 'unit' => 'پیام'],
            ],
        ],
        'lifecycle' => [
            'title' => 'مدیریت گفتگوها',
            'help' => 'بستن دستی گفتگوها و رسیدگی به گفتگوهای بی‌فعال.',
            'fields' => [
                ['path' => 'chat.close_threads', 'type' => 'toggle', 'icon' => 'fa-lock', 'label' => 'بستن و بازکردن گفتگو', 'help' => 'مشاور بتواند وضعیت گفتگو را تغییر دهد.'],
                ['path' => 'chat.idle_autoclose_days', 'type' => 'number', 'icon' => 'fa-clock', 'label' => 'بستن خودکار پس از بی‌فعالیتی', 'help' => 'از ۰ تا ۳۶۵ روز؛ ۰ یعنی بستن خودکار خاموش است.', 'min' => 0, 'max' => 365, 'unit' => 'روز'],
            ],
        ],
        'texts' => [
            'title' => 'متن‌های گفتگو',
            'help' => 'متن خالی به معنی استفاده از پیش‌فرض است.',
            'fields' => [
                ['path' => 'chat.greeting_text', 'type' => 'textarea', 'icon' => 'fa-comment', 'label' => 'پیام خوش‌آمد', 'help' => 'در حالت خالی گفتگو نمایش داده می‌شود؛ حداکثر ۵۰۰ نویسه.', 'max' => 500, 'rows' => 3],
                ['path' => 'chat.placeholder_text', 'type' => 'textarea', 'icon' => 'fa-keyboard', 'label' => 'راهنمای نوشتن پیام', 'help' => 'جای‌نویس فیلد پیام؛ حداکثر ۲۰۰ نویسه.', 'max' => 200, 'rows' => 2],
            ],
        ],
    ];
@endphp

<div class="profile-page profile-chat">


    @unless($isTenantAdmin)
        <p class="profile-chat-notice" role="note">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <span>حالت فقط خواندنی · فقط مدیر مجموعه می‌تواند این تنظیمات را تغییر دهد.</span>
        </p>
    @endunless

    <form method="POST" action="{{ route('consultant.settings.chat.save') }}" data-router="off">
        @csrf

        @foreach($chatSections as $sectionKey => $section)
            <section class="profile-chat-section" aria-labelledby="chat-section-{{ $sectionKey }}">
                <div class="profile-chat-section-head">
                    <h2 class="profile-chat-heading" id="chat-section-{{ $sectionKey }}">{{ $section['title'] }}</h2>

                </div>
                <div class="profile-settings-list">
                    @foreach($section['fields'] as $field)
                        @php
                            $name = str_replace('.', '_', $field['path']);
                            $id = 'chat-setting-'.$name;
                            $current = $chatConfig[$field['path']];
                            if ($field['path'] === 'chat.edit_window_minutes') {
                                $current = $current ?: '';
                            }
                            $value = old($name, $current);
                            $description = $errors->has($name) ? $id.'-error' : '';
                        @endphp
                        <div class="profile-chat-row profile-chat-row--{{ $field['type'] }}">
                            <span class="profile-setting-icon"><i class="fas {{ $field['icon'] }}" aria-hidden="true"></i></span>
                            <div class="profile-chat-copy">
                                <label class="profile-chat-label" id="{{ $id }}-label" for="{{ $id }}">{{ $field['label'] }}</label>

                                @error($name)
                                    <span class="settings-error" id="{{ $id }}-error">{{ $message }}</span>
                                @enderror
                            </div>

                            @if($field['type'] === 'toggle')
                                <span class="profile-chat-toggle">
                                    <input type="hidden" name="{{ $name }}" value="0" @disabled(! $isTenantAdmin)>
                                    <input type="checkbox" class="profile-chat-switch" role="switch"
                                           id="{{ $id }}" name="{{ $name }}" value="1"
                                           aria-labelledby="{{ $id }}-label" @if($description) aria-describedby="{{ $description }}" @endif
                                           aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
                                           @checked((string) $value === '1') @disabled(! $isTenantAdmin)>
                                </span>
                            @elseif($field['type'] === 'number')
                                <span class="profile-chat-number">
                                    <input type="number" class="profile-chat-number-input" dir="ltr" inputmode="numeric"
                                           id="{{ $id }}" name="{{ $name }}" value="{{ $value }}"
                                           min="{{ $field['min'] }}" max="{{ $field['max'] }}" step="1"
                                           placeholder="{{ $field['placeholder'] ?? '' }}"
                                           aria-labelledby="{{ $id }}-label" aria-describedby="{{ $description }} {{ $id }}-unit"
                                           aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
                                           @disabled(! $isTenantAdmin)>
                                    <span class="profile-chat-unit" id="{{ $id }}-unit">{{ $field['unit'] }}</span>
                                </span>
                            @else
                                <textarea class="settings-input profile-chat-text @error($name) is-invalid @enderror"
                                          id="{{ $id }}" name="{{ $name }}" rows="{{ $field['rows'] }}" maxlength="{{ $field['max'] }}"
                                          aria-labelledby="{{ $id }}-label" @if($description) aria-describedby="{{ $description }}" @endif
                                          aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
                                          @disabled(! $isTenantAdmin)>{{ $value }}</textarea>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if($isTenantAdmin)
            <div class="profile-chat-footer">

                <button type="submit" class="primary-button profile-chat-save">
                    <i class="fas fa-check" aria-hidden="true"></i> ذخیره تنظیمات
                </button>
            </div>
        @endif
    </form>

</div>


@endsection
