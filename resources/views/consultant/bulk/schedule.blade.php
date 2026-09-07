@extends('consultant.bulk.layout')

@php
    $days = ['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه'];
@endphp

@section('bulk-content')
<form method="POST" action="{{ route('consultant.bulk.schedule.store') }}" class="bulk-form" data-router="off">
    @csrf

    <div class="settings-cards">
        <section class="settings-card">
            <h3 class="settings-card-title">۱. بلوک هفتگی</h3>

            <label class="settings-field">
                <span class="settings-field-label">عنوان</span>
                <input type="text" name="title" value="{{ old('title') }}" required class="settings-input @error('title') is-invalid @enderror">
                @error('title')<span class="settings-error">{{ $message }}</span>@enderror
            </label>

            <div class="settings-field-row">
                <label class="settings-field">
                    <span class="settings-field-label">هفتهٔ شروع</span>
                    <input type="date" name="week_start_date" value="{{ old('week_start_date', $weekStart) }}" class="settings-input">
                </label>
                <label class="settings-field">
                    <span class="settings-field-label">روز</span>
                    <select name="day_index" class="settings-input">
                        @foreach($days as $i => $day)
                            <option value="{{ $i }}" @selected(old('day_index') == $i)>{{ $day }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="settings-field">
                    <span class="settings-field-label">شروع</span>
                    <input type="time" name="start_time" value="{{ old('start_time') }}" required class="settings-input">
                </label>
                <label class="settings-field">
                    <span class="settings-field-label">پایان</span>
                    <input type="time" name="end_time" value="{{ old('end_time') }}" required class="settings-input @error('end_time') is-invalid @enderror">
                </label>
            </div>

            <div class="settings-field-row">
                <label class="settings-field">
                    <span class="settings-field-label">رنگ</span>
                    <input type="color" name="color" value="{{ old('color', '#3b82f6') }}" class="settings-input settings-input--color">
                </label>
                <label class="settings-field">
                    <span class="settings-field-label">کتاب</span>
                    <input type="text" name="book_name" value="{{ old('book_name') }}" class="settings-input">
                </label>
                <label class="settings-field">
                    <span class="settings-field-label">تعداد تست</span>
                    <input type="number" name="test_count" min="0" value="{{ old('test_count') }}" class="settings-input">
                </label>
                <label class="settings-field">
                    <span class="settings-field-label">تعداد صفحه</span>
                    <input type="number" name="page_count" min="0" value="{{ old('page_count') }}" class="settings-input">
                </label>
            </div>

            <label class="settings-field">
                <span class="settings-field-label">توضیحات</span>
                <textarea name="description" rows="2" class="settings-input">{{ old('description') }}</textarea>
            </label>
        </section>

        <section class="settings-card">
            <h3 class="settings-card-title">۲. دانش‌آموزان</h3>
            @include('consultant.bulk.partials._picker')
            @error('students')<span class="settings-error">{{ $message }}</span>@enderror
        </section>

        <div class="blog-form-actions">
            <button type="submit" class="primary-button">
                <i class="fas fa-paper-plane" aria-hidden="true"></i> ثبت گروهی برنامه
            </button>
        </div>
    </div>
</form>

@vite(['resources/js/features/bulk-actions.js'])
@endsection
