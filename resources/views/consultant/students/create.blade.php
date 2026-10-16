{{--
    افزودن دانش‌آموز — the admin types a student's data in himself.

    The second way onto the roster, alongside درخواست: for a person assigned
    outside the request flow, with no visitor and no declared payment behind
    them. Only the roster's own columns are collected — there is no payment
    field here, because nothing is being verified (that is what درخواست is for).

    Reuses the settings form contract (.settings-cards/.settings-card/
    .settings-form/.settings-field/.settings-input/.settings-error) — no new CSS.
--}}
@extends('layouts.consultant')

@section('content')
<div class="settings-cards">

    <section class="settings-card">
        <h3 class="settings-card-title">
            <i class="fas fa-user-plus" aria-hidden="true"></i> افزودن دانش‌آموز
        </h3>
        <p class="settings-card-text">
            اطلاعات دانش‌آموزی را که خودتان به او دسترسی داده‌اید اینجا وارد کنید.
            نام و شمارهٔ موبایل الزامی است؛ بقیهٔ موارد اختیاری‌اند. پس از ثبت، یک رمز عبور
            ساخته می‌شود که فقط یک‌بار نمایش داده خواهد شد.
        </p>

        @if(session('status'))
            <div class="settings-flash settings-flash--success">
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="settings-flash settings-flash--error">
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('consultant.requests.store') }}" class="settings-form">
            @csrf

            <div class="settings-field">
                <label class="settings-field-label" for="create-name">نام و نام خانوادگی</label>
                <input type="text" id="create-name" name="name" value="{{ old('name') }}"
                       maxlength="255" required
                       class="settings-input @error('name') is-invalid @enderror">
                @error('name') <span class="settings-error">{{ $message }}</span> @enderror
            </div>

            <div class="settings-field">
                <label class="settings-field-label" for="create-phone">شمارهٔ موبایل (نام کاربری ورود)</label>
                <input type="tel" id="create-phone" name="phone" value="{{ old('phone') }}"
                       maxlength="40" required dir="ltr"
                       class="settings-input @error('phone') is-invalid @enderror">
                @error('phone') <span class="settings-error">{{ $message }}</span> @enderror
            </div>

            <div class="settings-field">
                <label class="settings-field-label" for="create-email">ایمیل (اختیاری)</label>
                <input type="email" id="create-email" name="email" value="{{ old('email') }}"
                       maxlength="255" dir="ltr"
                       class="settings-input @error('email') is-invalid @enderror">
                @error('email') <span class="settings-error">{{ $message }}</span> @enderror
            </div>

            <div class="settings-field">
                <label class="settings-field-label" for="create-national-code">کد ملی (اختیاری)</label>
                <input type="text" id="create-national-code" name="national_code" value="{{ old('national_code') }}"
                       maxlength="20" dir="ltr"
                       class="settings-input @error('national_code') is-invalid @enderror">
                @error('national_code') <span class="settings-error">{{ $message }}</span> @enderror
            </div>

            {{-- پایه تحصیلی، جنسیت و رشته — fixed choice lists, not typing.
                 Static on purpose: a fresh roster (nothing to derive options
                 from) must still get drop-downs, so the three lists live in
                 the controller's *_CHOICES constants. --}}
            @foreach([
                'grade' => ['label' => 'پایه تحصیلی', 'options' => $gradeOptions],
                'gender' => ['label' => 'جنسیت', 'options' => $genderOptions],
                'major' => ['label' => 'رشته تحصیلی', 'options' => $majorOptions],
            ] as $field => $meta)
                @php($current = old($field))
                <div class="settings-field">
                    <label class="settings-field-label" for="create-{{ $field }}">{{ $meta['label'] }}</label>
                    <select id="create-{{ $field }}" name="{{ $field }}"
                            class="settings-input @error($field) is-invalid @enderror">
                        <option value="">— انتخاب کنید —</option>
                        @if($current && ! $meta['options']->contains($current))
                            {{-- A value outside today's list (the list changed
                                 while the form was open): keep the admin's
                                 pick rather than silently reset it. --}}
                            <option value="{{ $current }}" selected>{{ $current }}</option>
                        @endif
                        @foreach($meta['options'] as $option)
                            <option value="{{ $option }}" @selected($current === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    @error($field) <span class="settings-error">{{ $message }}</span> @enderror
                </div>
            @endforeach

            <div class="settings-field">
                <label class="settings-field-label" for="create-bio">توضیح کوتاه</label>
                <textarea id="create-bio" name="bio" rows="3" maxlength="500"
                          class="settings-input @error('bio') is-invalid @enderror">{{ old('bio') }}</textarea>
                @error('bio') <span class="settings-error">{{ $message }}</span> @enderror
            </div>

            {{-- Optional: only a hierarchical consultancy has consultants to
                 assign to. A non-hierarchical tenant's admin is the consultant,
                 and a school groups by classroom (out of scope here) — so the
                 select simply does not render for either. --}}
            @if($consultants->isNotEmpty())
                <div class="settings-field">
                    <label class="settings-field-label" for="create-consultant">مشاور (اختیاری)</label>
                    <select id="create-consultant" name="consultant_id"
                            class="settings-input @error('consultant_id') is-invalid @enderror">
                        <option value="">— بدون مشاور —</option>
                        @foreach($consultants as $consultant)
                            <option value="{{ $consultant->id }}"
                                @selected((string) old('consultant_id') === (string) $consultant->id)>
                                {{ $consultant->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('consultant_id') <span class="settings-error">{{ $message }}</span> @enderror
                    <span class="settings-card-text">
                        در مجموعه‌های چندمشاوری، دانش‌آموز فقط برای مشاور متصل‌شده نمایش داده می‌شود.
                    </span>
                </div>
            @endif

            <button type="submit" class="primary-button">
                <i class="fas fa-user-plus" aria-hidden="true"></i> افزودن به فهرست
            </button>
        </form>
    </section>

    <section class="settings-card">
        <a href="{{ route('consultant.dashboard') }}" class="btn">
            <i class="fas fa-arrow-right" aria-hidden="true"></i> بازگشت به فهرست دانش‌آموزان
        </a>
    </section>
</div>
@endsection
