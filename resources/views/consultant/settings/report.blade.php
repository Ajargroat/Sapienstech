{{--
    Profile hub → کارنامه (report) section.

    Two tenant values the consultant owns, both written to `report.*` in the
    tenant's runtime layer through ConfigWriter (never into config/tenants/*):

      report.pass_line  — the percentage a subject is judged against. Drives the
                          student کارنامه's «نقاط ضعف» list, the rail markers and
                          the passed/not-passed colours.
      report.report_show_class_average — whether the participants' average may
                          be shown next to a subject. Off by default: comparing a
                          student with the crowd is the consultant's call.

    Rendered read-only for anyone who is not the tenant admin, exactly like the
    chat section next door. The field markup intentionally reuses the
    `profile-chat-*` / `profile-settings-list` kit so the hub has ONE settings
    idiom rather than a second one that drifts.
--}}
@extends('consultant.settings.layout')

@section('settings-content')
@php
    $reportSections = [
        'grading' => [
            'title' => 'خط قبولی و مقایسه',
            'help' => 'درصدی که یک درس با آن سنجیده می‌شود، و اینکه میانگین شرکت‌کنندگان نمایش داده شود یا نه.',
            'fields' => [
                ['path' => 'report.pass_line', 'type' => 'number', 'icon' => 'fa-gauge-high',
                 'label' => 'خط قبولی هر درس', 'help' => 'از ۰ تا ۱۰۰ درصد. درسی که آخرین درصدش زیر این عدد باشد در «نقاط ضعف» کارنامهٔ دانش‌آموز می‌آید. پیش‌فرض ۵۰.',
                 'min' => 0, 'max' => 100, 'unit' => 'درصد', 'placeholder' => '50'],
                ['path' => 'report.report_show_class_average', 'type' => 'toggle', 'icon' => 'fa-users',
                 'label' => 'نمایش میانگین شرکت‌کنندگان', 'help' => 'فقط برای کارنامه‌هایی که با عدد شرکت‌کنندگان وارد شده‌اند نمایش داده می‌شود. خاموش باشد، دانش‌آموز فقط روند خودش را می‌بیند.'],
            ],
        ],
    ];
@endphp

<div class="profile-page profile-chat">

    <section class="profile-chat-section" aria-labelledby="report-preview">
        <div class="profile-chat-section-head">
            <h2 class="profile-chat-heading" id="report-preview">پیش‌نمایش اثر</h2>
        </div>
        <div class="profile-settings-list">
            <div class="profile-chat-row">
                <span class="profile-setting-icon"><i class="fas fa-chart-pie" aria-hidden="true"></i></span>
                <div class="profile-chat-copy">
                    <span class="profile-chat-label">آنچه دانش‌آموز الان می‌بیند</span>
                    <span class="profile-chat-help">
                        خط قبولی روی {{ persian_digits(rtrim(rtrim(number_format($passLine, 1, '.', ''), '0'), '.')) }}٪ تنظیم است؛
                        هر درس زیر این عدد در فهرست «نقاط ضعف این دوره» می‌آید.
                        میانگین شرکت‌کنندگان {{ $showsClassAverage ? 'نمایش داده می‌شود' : 'نمایش داده نمی‌شود' }}.
                    </span>
                </div>
            </div>
        </div>
    </section>

    @unless($isTenantAdmin)
        <p class="profile-chat-notice" role="note">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <span>حالت فقط خواندنی · فقط مدیر مجموعه می‌تواند این تنظیمات را تغییر دهد.</span>
        </p>
    @endunless

    <form method="POST" action="{{ route('consultant.settings.report.save') }}" data-router="off">
        @csrf

        @foreach($reportSections as $sectionKey => $section)
            <section class="profile-chat-section" aria-labelledby="report-section-{{ $sectionKey }}">
                <div class="profile-chat-section-head">
                    <h2 class="profile-chat-heading" id="report-section-{{ $sectionKey }}">{{ $section['title'] }}</h2>
                </div>
                <div class="profile-settings-list">
                    @foreach($section['fields'] as $field)
                        @php
                            $name = str_replace('.', '_', $field['path']);
                            $id = 'report-setting-'.$name;
                            $value = old($name, $values[$field['path']]);
                            $description = $errors->has($name) ? $id.'-error' : '';
                        @endphp
                        <div class="profile-chat-row profile-chat-row--{{ $field['type'] }}">
                            <span class="profile-setting-icon"><i class="fas {{ $field['icon'] }}" aria-hidden="true"></i></span>
                            <div class="profile-chat-copy">
                                <label class="profile-chat-label" id="{{ $id }}-label" for="{{ $id }}">{{ $field['label'] }}</label>
                                <span class="profile-chat-help">{{ $field['help'] }}</span>

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
                            @else
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
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if($isTenantAdmin)
            <div class="profile-chat-footer">
                <button type="submit" class="primary-button profile-chat-save">
                    <i class="fas fa-check" aria-hidden="true"></i> ذخیرهٔ تنظیمات
                </button>
            </div>
        @endif
    </form>

</div>
@endsection
